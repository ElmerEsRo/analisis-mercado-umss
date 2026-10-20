<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/xlsx_reader.php';

function norm(string $s): string {
    $s = trim(strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',"\xc2\xa0"=>' ']));
    $s = strtolower($s);
    return preg_replace('/\s+/', ' ', $s);
}

function useful_header(string $h): bool {
    $n = norm($h);
    if ($n === '' || str_starts_with($n,'puntos:') || str_starts_with($n,'comentarios:')) return false;
    if (in_array($n,['total de puntos','comentarios del cuestionario','hora de publicacion de la calificacion','id','nombre'],true)) return false;
    foreach (['marca temporal','hora de inicio','hora de finalizacion','hora de publicacion','correo electronico','email de contacto','numero telefonico','link de linkedin'] as $x) {
        if (str_contains($n,$x)) return false;
    }
    return true;
}

function read_tabular_file(string $file,string $ext): array {
    $ext=strtolower($ext);
    if($ext==='xlsx') return SimpleXlsxReader::read($file);
    if($ext==='csv'){
        $rows=[];$fh=fopen($file,'r');if(!$fh)throw new RuntimeException('No se pudo abrir el CSV.');
        $first=fgets($fh);if($first===false)return []; $delimiter=substr_count($first,';')>substr_count($first,',')?';':','; rewind($fh);
        while(($r=fgetcsv($fh,0,$delimiter))!==false)$rows[]=$r; fclose($fh); return $rows;
    }
    throw new RuntimeException('Formato no permitido. Use XLSX o CSV.');
}

function import_dataset(string $type,string $tmpFile,string $originalName): int {
    $ext=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));$rows=read_tabular_file($tmpFile,$ext);
    if(count($rows)<2)throw new RuntimeException('El archivo no contiene respuestas suficientes.');
    $headers=array_map(fn($x)=>trim((string)$x),$rows[0]);$keep=[];
    foreach($headers as $i=>$h)if(useful_header($h))$keep[$i]=$h;
    if(count($keep)<3)throw new RuntimeException('No se detectaron columnas válidas.');
    $pdo=db();$pdo->beginTransaction();
    try{
        if(db_driver()==='pgsql'){
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?) RETURNING id');
            $st->execute([$type,$originalName,count($rows)-1,count($keep),json_encode(array_values($keep),JSON_UNESCAPED_UNICODE)]);
            $id=(int)$st->fetchColumn();
        }else{
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?)');
            $st->execute([$type,$originalName,count($rows)-1,count($keep),json_encode(array_values($keep),JSON_UNESCAPED_UNICODE)]);
            $id=(int)$pdo->lastInsertId();
        }
        $ins=$pdo->prepare('INSERT INTO responses(dataset_id,row_number,data_json) VALUES(?,?,?)');$saved=0;
        for($i=1;$i<count($rows);$i++){
            $assoc=[];$nonEmpty=false;
            foreach($keep as $c=>$h){$v=trim((string)($rows[$i][$c]??''));if($v!=='')$nonEmpty=true;$assoc[$h]=$v;}
            if(!$nonEmpty)continue;$ins->execute([$id,$i+1,json_encode($assoc,JSON_UNESCAPED_UNICODE)]);$saved++;
        }
        $pdo->prepare('UPDATE datasets SET rows_count=? WHERE id=?')->execute([$saved,$id]);$pdo->commit();return $id;
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}


function parse_dataset_rows(string $tmpFile,string $originalName): array {
    $ext=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
    $rows=read_tabular_file($tmpFile,$ext);
    if(count($rows)<2)throw new RuntimeException('El archivo no contiene respuestas suficientes.');

    $headers=array_map(fn($x)=>trim((string)$x),$rows[0]);
    $keep=[];
    foreach($headers as $i=>$h)if(useful_header($h))$keep[$i]=$h;
    if(count($keep)<3)throw new RuntimeException('No se detectaron columnas válidas.');

    $cleanRows=[];
    for($i=1;$i<count($rows);$i++){
        $assoc=[];$nonEmpty=false;
        foreach($keep as $c=>$h){
            $v=trim((string)($rows[$i][$c]??''));
            if($v!=='')$nonEmpty=true;
            $assoc[$h]=$v;
        }
        if($nonEmpty)$cleanRows[]=$assoc;
    }
    if(!$cleanRows)throw new RuntimeException('El archivo no contiene respuestas válidas.');

    return [array_values($keep),$cleanRows];
}

function save_dataset_rows(string $type,string $filename,array $headers,array $rows): int {
    $pdo=db();
    $pdo->beginTransaction();
    try{
        if(db_driver()==='pgsql'){
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?) RETURNING id');
            $st->execute([$type,$filename,count($rows),count($headers),json_encode($headers,JSON_UNESCAPED_UNICODE)]);
            $id=(int)$st->fetchColumn();
        }else{
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?)');
            $st->execute([$type,$filename,count($rows),count($headers),json_encode($headers,JSON_UNESCAPED_UNICODE)]);
            $id=(int)$pdo->lastInsertId();
        }

        $ins=$pdo->prepare('INSERT INTO responses(dataset_id,row_number,data_json) VALUES(?,?,?)');
        foreach($rows as $i=>$row){
            $normalized=[];
            foreach($headers as $h)$normalized[$h]=(string)($row[$h]??'');
            $ins->execute([$id,$i+1,json_encode($normalized,JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
        return $id;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function replace_dataset(string $type,string $tmpFile,string $originalName): int {
    [$headers,$rows]=parse_dataset_rows($tmpFile,$originalName);
    $id=save_dataset_rows($type,$originalName,$headers,$rows);

    // La nueva base pasa a ser la única base activa de este tipo.
    $st=db()->prepare('DELETE FROM datasets WHERE type=? AND id<>?');
    $st->execute([$type,$id]);
    return $id;
}

function append_dataset(string $type,string $tmpFile,string $originalName): array {
    [$newHeaders,$newRows]=parse_dataset_rows($tmpFile,$originalName);

    $current=latest_dataset($type);
    $existingRows=[];
    $existingHeaders=[];

    if($current && (int)$current['rows_count']>0){
        $existingRows=dataset_rows((int)$current['id']);
        $existingHeaders=json_decode($current['headers_json']??'[]',true)?:[];
    }

    // Conserva el orden de las columnas actuales y añade columnas nuevas al final.
    $headers=$existingHeaders;
    foreach($newHeaders as $h)if(!in_array($h,$headers,true))$headers[]=$h;

    $allRows=array_merge($existingRows,$newRows);
    $id=save_dataset_rows($type,'Base acumulada - '.$originalName,$headers,$allRows);

    // Se elimina la versión anterior para evitar duplicar almacenamiento.
    $st=db()->prepare('DELETE FROM datasets WHERE type=? AND id<>?');
    $st->execute([$type,$id]);

    return ['id'=>$id,'added'=>count($newRows),'total'=>count($allRows)];
}

function clear_dataset(string $type): void {
    $pdo=db();
    $pdo->beginTransaction();
    try{
        // responses se eliminan por ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM datasets WHERE type=?')->execute([$type]);

        // Se deja un registro vacío para evitar que AUTO_SEED vuelva a precargar la base.
        if(db_driver()==='pgsql'){
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?)');
        }else{
            $st=$pdo->prepare('INSERT INTO datasets(type,filename,rows_count,columns_count,headers_json) VALUES(?,?,?,?,?)');
        }
        $st->execute([$type,'Base vacía',0,0,'[]']);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function latest_dataset(string $type): ?array {
    $st=db()->prepare('SELECT * FROM datasets WHERE type=? ORDER BY id DESC LIMIT 1');
    $st->execute([$type]);
    $r=$st->fetch();
    if($r) return $r;

    $autoSeed = getenv('AUTO_SEED');
    if($autoSeed === false || $autoSeed === '' || $autoSeed === '1' || strtolower($autoSeed) === 'true'){
        $file=__DIR__.'/../datos/base_'.$type.'.csv';
        if(is_file($file)){
            import_dataset($type,$file,basename($file));
            $st=db()->prepare('SELECT * FROM datasets WHERE type=? ORDER BY id DESC LIMIT 1');
            $st->execute([$type]);
            $r=$st->fetch();
            if($r) return $r;
        }
    }
    return null;
}
function dataset_rows(int $id): array {
    $st=db()->prepare('SELECT data_json FROM responses WHERE dataset_id=? ORDER BY row_number');$st->execute([$id]);
    return array_map(fn($r)=>json_decode($r['data_json'],true)?:[],$st->fetchAll());
}
function find_header(array $headers,array $needles): ?string {
    foreach($headers as $h){$n=norm($h);foreach($needles as $needle)if(str_contains($n,norm($needle)))return $h;}return null;
}

function header_aliases(string $type): array {
    if($type==='titulados')return [
        'edad'=>['edad que tiene actualmente'],'genero'=>['genero:'],
        'formacion_complementaria'=>['ha realizado o se encuentra realizando algun programa de formacion complementaria'],
        'nivel_posgrado'=>['programa de formacion complementaria de mayor nivel'],
        'institucion_posgrado'=>['donde ha cursado el programa indicado'],
        'financiamiento'=>['fuente de financiamiento para cursar el programa indicado'],
        'interes_posgrado'=>['estaria interesado en realizar estudios en algun programa de posgrado'],
        'area_interes'=>['en cual de las siguientes areas le interesaria realizar el programa'],
        'institucion_futura'=>['en que organizacion educativa optaria por realizar sus estudios de posgrado'],
        'opinion_posgrado'=>['opinion personal respecto al posgrado de la fcyt'],
        'vida_profesional'=>['anos de vida profesional'],'desempleo'=>['total de tiempo en que se ha encontrado desempleado'],
        'situacion_laboral'=>['afirmaciones describe su situacion laboral actual'],
        'empresa_actual'=>['nombre de la empresa u organizacion donde trabaja actualmente'],
        'rubro'=>['rubro de la organizacion donde trabaja actualmente'],'sector'=>['a que sector corresponde'],
        'departamento'=>['en que departamento de bolivia trabaja'],'area_trabajo'=>['area dentro de la organizacion donde desarrolla sus actividades profesionales'],
        'cargo'=>['cual es el cargo que desempena en su actual trabajo'],'pertinencia'=>['labores que se encuentra desempenando son pertinentes'],
        'antiguedad_trabajo'=>['que antiguedad tiene en su actual trabajo'],'medio_empleo'=>['a traves de que medio obtuvo el trabajo'],
        'remuneracion'=>['remuneracion promedio mensual'],'origen_emprendimiento'=>['origen del desarrollo de su propio emprendimiento'],
        'entregable_negocio'=>['que tipo de entregable genera el negocio'],'financiamiento_negocio'=>['ha requerido financiamiento externo para su negocio'],
        'satisfaccion_negocio'=>['que tan satisfecho esta con el rendimiento actual de su negocio'],
        'importancia_formacion_negocio'=>['que tan importante considera que fue su formacion en ingenieria en sistemas'],
        'primer_empleo'=>['el trabajo que ejerce actualmente es su primer empleo'],'razon_no_relacionado'=>['razon por la que actualmente no trabaja en un empleo relacionado'],
        'trabajo_previo'=>['si bien actualmente no trabaja, ha tenido algun trabajo antes'],'tiempo_primer_empleo'=>['cuanto tiempo se demoro en conseguir su primer trabajo'],
        'num_empleos'=>['en cuantos empleos usted se ha desempenado'],'satisfaccion_formacion'=>['grado de satisfaccion que ha desarrollado con la formacion profesional'],
        'concordancia_formacion'=>['considera que existe concordancia entre la formacion profesional'],'aspectos_utiles'=>['que aspectos de la carrera les resultaron de bastante utilidad'],
        'aspectos_mejorar'=>['que aspectos de la carrera considera que pueden mejorarse'],'asignaturas_ventaja'=>['asignaturas cursadas en la carrera que considera que le brindaron una ventaja competitiva'],
        'asignaturas_no_utiles'=>['asignaturas cursadas en la carrera que no le resultaron de mucha utilidad'],'red_contactos'=>['le interesaria formar parte una red de contactos oficial']
    ];
    return [
        'empresa'=>['nombre de la empresa o institucion'],'tipo_org'=>['tipo de organizacion'],'tamano_org'=>['tamano de la organizacion'],
        'rubro'=>['indique el rubro o sector principal'],'contrato_5'=>['ha contratado ingenieros de la carrera de sistemas en los ultimos 5 anos'],
        'contratacion_futura'=>['posibilidad de incorporar ingenieros de sistemas'],'formacion_demandada'=>['nivel de formacion academica o actualizacion profesional'],
        'medio_convoca'=>['a traves de que medio convoca a profesionales'],'cargos'=>['que tipo de cargos desempenan los profesionales titulados'],
        'tendencias'=>['nuevas areas de conocimiento, tecnologias o tendencias emergentes'],'herramientas'=>['nuevas herramientas tecnologicas'],
        'habilidades'=>['habilidades, competencias y destrezas']
    ];
}
function resolve_headers(array $headers,string $type): array {$o=[];foreach(header_aliases($type) as $k=>$n)$o[$k]=find_header($headers,$n);return $o;}
function dataset_bundle(string $type): ?array {$ds=latest_dataset($type);if(!$ds)return null;$headers=json_decode($ds['headers_json'],true)?:[];$rows=dataset_rows((int)$ds['id']);return ['dataset'=>$ds,'headers'=>$headers,'rows'=>$rows,'map'=>resolve_headers($headers,$type)];}
function values_for(array $rows,?string $header): array {if(!$header)return [];$o=[];foreach($rows as $r){$v=trim((string)($r[$header]??''));if($v!=='')$o[]=$v;}return $o;}

function split_multi_value(string $value): array {
    $value=trim($value);if($value==='')return [];
    if(str_starts_with($value,'[')&&str_ends_with($value,']')){$d=json_decode($value,true);if(is_array($d))return array_values(array_filter(array_map(fn($x)=>trim((string)$x),$d),fn($x)=>$x!==''));}
    $out=[];$buf='';$depth=0;$len=strlen($value);
    for($i=0;$i<$len;$i++){$ch=$value[$i];if($ch==='(')$depth++;if($ch===')'&&$depth>0)$depth--;if(($ch===','||$ch===';')&&$depth===0){$p=trim($buf);if($p!=='')$out[]=$p;$buf='';}else{$buf.=$ch;}}
    $p=trim($buf);if($p!=='')$out[]=$p;return $out?:[$value];
}
function frequencies(array $values,bool $splitMulti=false): array {$f=[];foreach($values as $v){$parts=$splitMulti?split_multi_value((string)$v):[trim((string)$v)];foreach($parts as $p){$p=trim($p);if($p==='')continue;$f[$p]=($f[$p]??0)+1;}}arsort($f);return $f;}
function numeric_values(array $values): array {$o=[];foreach($values as $v){$v=str_replace(',','.',$v);if(preg_match('/-?\d+(?:\.\d+)?/',$v,$m))$o[]=(float)$m[0];}return $o;}
function mean(array $x): ?float{return count($x)?array_sum($x)/count($x):null;}
function median(array $x): ?float{if(!$x)return null;sort($x,SORT_NUMERIC);$n=count($x);return $n%2?$x[intdiv($n,2)]:($x[$n/2-1]+$x[$n/2])/2;}
function stddev(array $x): ?float{$n=count($x);if($n<2)return $n?0:null;$m=mean($x);$s=0;foreach($x as $v)$s+=($v-$m)**2;return sqrt($s/($n-1));}
function count_match(array $freq,array $needles): int {$s=0;foreach($freq as $k=>$v){$n=norm($k);foreach($needles as $q){if(str_contains($n,norm($q))){$s+=$v;break;}}}return $s;}

function likert_score(string $v): ?float {
    $n=norm($v);$map=['totalmente en desacuerdo'=>1,'totalmente desacuerdo'=>1,'insatisfecho'=>1,'no es importante'=>1,'en desacuerdo'=>2,'algo satisfecho'=>2,'poco importante'=>2,'parcialmente de acuerdo'=>3,'de acuerdo'=>3,'satisfecho'=>3,'importante'=>3,'totalmente de acuerdo'=>4,'muy satisfecho'=>4,'muy importante'=>4,'muy de acuerdo'=>4,'no sabe'=>null];
    foreach($map as $k=>$score)if($n===$k||str_contains($n,$k))return $score;if(is_numeric($v)){$f=(float)$v;return ($f>=1&&$f<=5)?$f:null;}return null;
}
function likert_stats(array $rows,?string $header,float $max=4): ?array {if(!$header)return null;$x=[];foreach(values_for($rows,$header) as $v){$s=likert_score($v);if($s!==null)$x[]=$s;}if(!$x)return null;$m=mean($x);return ['mean'=>$m,'sd'=>stddev($x),'n'=>count($x),'gap'=>max(0,$max-$m),'index'=>$max>0?$m/$max*100:0];}

function likert_stats_1to5(array $rows,?string $header): ?array {
    if(!$header)return null;
    $x=[];
    foreach(values_for($rows,$header) as $v){
        $s=likert_score($v);
        if($s!==null){
            // Las encuestas disponibles usan cuatro niveles. Se conserva el orden y se expresa
            // linealmente en una escala equivalente 1-5 para el análisis solicitado.
            $x[]=1+(($s-1)*4/3);
        }
    }
    if(!$x)return null;
    return ['mean'=>mean($x),'sd'=>stddev($x),'n'=>count($x)];
}

function contingency(array $rows,string $ha,string $hb): array {
    $aCats=[];$bCats=[];$pairs=[];foreach($rows as $r){$a=trim((string)($r[$ha]??''));$b=trim((string)($r[$hb]??''));if($a===''||$b==='')continue;$aCats[$a]=1;$bCats[$b]=1;$pairs[]=[$a,$b];}
    $aCats=array_keys($aCats);sort($aCats);$bCats=array_keys($bCats);sort($bCats);$mx=[];foreach($aCats as $a)$mx[$a]=array_fill_keys($bCats,0);foreach($pairs as[$a,$b])$mx[$a][$b]++;return [$aCats,$bCats,$mx,count($pairs)];
}
function chi_square_test(array $matrix,array $aCats,array $bCats): array {
    $r=count($aCats);$c=count($bCats);if($r<2||$c<2)return ['chi2'=>null,'df'=>0,'p'=>null,'valid'=>false,'warning'=>'Se necesitan al menos dos categorías por variable.'];
    $row=[];$col=array_fill_keys($bCats,0);$n=0;foreach($aCats as $a){$row[$a]=array_sum($matrix[$a]);$n+=$row[$a];foreach($bCats as $b)$col[$b]+=$matrix[$a][$b];}if(!$n)return ['chi2'=>null,'df'=>0,'p'=>null,'valid'=>false,'warning'=>'No hay casos válidos.'];
    $chi=0.0;$low=0;$cells=$r*$c;foreach($aCats as $a)foreach($bCats as $b){$e=$row[$a]*$col[$b]/$n;if($e>0){if($e<5)$low++;$chi+=(($matrix[$a][$b]-$e)**2)/$e;}}
    $df=($r-1)*($c-1);$p=gammq($df/2,$chi/2);$warning=$low/$cells>0.2?'Más del 20% de las frecuencias esperadas son menores a 5; interpreta la prueba con cautela.':'';
    return ['chi2'=>$chi,'df'=>$df,'p'=>$p,'valid'=>true,'warning'=>$warning];
}
function gammln(float $xx): float{$cof=[76.18009172947146,-86.50532032941677,24.01409824083091,-1.231739572450155,0.001208650973866179,-0.000005395239384953];$x=$xx;$y=$xx;$tmp=$x+5.5;$tmp-=($x+0.5)*log($tmp);$ser=1.000000000190015;foreach($cof as $j)$ser+=$j/++$y;return -$tmp+log(2.5066282746310005*$ser/$x);}
function gammq(float $a,float $x): float{if($x<0||$a<=0)return NAN;if($x<$a+1)return 1-gser($a,$x);return gcf($a,$x);}
function gser(float $a,float $x): float{if($x<=0)return 0.0;$ap=$a;$sum=1/$a;$del=$sum;for($n=1;$n<=100;$n++){$ap++;$del*=$x/$ap;$sum+=$del;if(abs($del)<abs($sum)*3e-7)break;}return $sum*exp(-$x+$a*log($x)-gammln($a));}
function gcf(float $a,float $x): float{$fp=1e-30;$b=$x+1-$a;$c=1/$fp;$d=1/$b;$h=$d;for($i=1;$i<=100;$i++){$an=-$i*($i-$a);$b+=2;$d=$an*$d+$b;if(abs($d)<$fp)$d=$fp;$c=$b+$an/$c;if(abs($c)<$fp)$c=$fp;$d=1/$d;$del=$d*$c;$h*=$del;if(abs($del-1)<3e-7)break;}return exp(-$x+$a*log($x)-gammln($a))*$h;}

function thematic_frequencies(array $values,string $mode): array {
    $defs=[];
    if($mode==='tendencias')$defs=[
        'Inteligencia Artificial'=>['inteligencia artificial',' ia ','ia,','ia.','agente','machine learning','ml'],
        'Ciberseguridad'=>['ciberseguridad','seguridad informatica','seguridad de sistemas','ciber seguridad'],
        'Datos y analítica'=>['analisis de datos','análisis de datos','big data','business intelligence','inteligencia de negocios','data science'],
        'Cloud / DevOps'=>['cloud','nube','devops','aws','azure','gcp'],
        'Desarrollo de software'=>['ingenieria de software','desarrollo de software','programacion','programación','apps moviles','aplicaciones moviles'],
        'Automatización'=>['automatizacion','automatización','autocontrol'],
        'Redes e infraestructura'=>['redes','infraestructura','telecomunicaciones'],
        'Gestión y negocio'=>['gestion','gestión','negocio','bpm','agil','ágil']
    ];
    elseif($mode==='herramientas')$defs=[
        'Herramientas de IA'=>['inteligencia artificial',' ia ','claude','antigravity','agente'],
        'Git / GitHub'=>['github',' git ','git/'],
        'Cloud'=>['aws','azure','gcp','cloud','nube'],
        'Python'=>['python'],'Java / Kotlin'=>['java','kotlin'],
        'DevOps / CI-CD'=>['devops','ci/cd','pipeline'],'Entornos de desarrollo'=>['visual studio','visual code','vscode','jetbrains','entorno de desarrollo'],
        'Gestión ágil'=>['jira','trello','scrum','agil','ágil'],'APIs / pruebas'=>['postman','api'],
        'Contenedores'=>['docker','kubernetes']
    ];
    elseif($mode==='hard')$defs=[
        'Desarrollo de software'=>['desarrollo de software','ingenieria de software','programacion','programación','software'],
        'Bases de datos y datos'=>['base de datos','bases de datos','datos','analitica','analítica','business intelligence','data science'],
        'Ciberseguridad'=>['ciberseguridad','seguridad informatica','seguridad informática','seguridad de sistemas'],
        'Redes e infraestructura'=>['redes','infraestructura','telecomunicaciones','servidores'],
        'IA y automatización'=>['inteligencia artificial',' ia ','machine learning','automatizacion','automatización'],
        'Cloud / DevOps'=>['cloud','nube','devops','aws','azure','gcp','docker','kubernetes'],
        'Arquitectura y análisis de sistemas'=>['arquitectura','analisis de sistemas','análisis de sistemas','sistemas']
    ];
    elseif($mode==='soft')$defs=[
        'Comunicación'=>['comunicacion','comunicación'],
        'Trabajo en equipo'=>['trabajo en equipo','equipo'],
        'Resolución de problemas'=>['resolucion de problemas','resolución de problemas','resolver problemas'],
        'Pensamiento crítico'=>['pensamiento critico','pensamiento crítico'],
        'Aprendizaje continuo'=>['aprendizaje continuo','autoaprendizaje','actualizacion','actualización'],
        'Adaptabilidad'=>['adaptabilidad','adaptacion','adaptación','flexibilidad'],
        'Liderazgo e iniciativa'=>['liderazgo','iniciativa'],
        'Ética y responsabilidad'=>['etica','ética','responsabilidad']
    ];
    else $defs=[
        'Comunicación'=>['comunicacion','comunicación'],'Trabajo en equipo'=>['trabajo en equipo','equipo'],
        'Resolución de problemas'=>['resolucion de problemas','resolución de problemas','resolver problemas','analisis','análisis'],
        'Pensamiento crítico'=>['pensamiento critico','pensamiento crítico'],'Aprendizaje continuo'=>['aprendizaje continuo','autoaprendizaje','actualizacion','actualización'],
        'Adaptabilidad'=>['adaptabilidad','adaptacion','adaptación','flexibilidad'],'Liderazgo / iniciativa'=>['liderazgo','iniciativa'],
        'Ética y responsabilidad'=>['etica','ética','responsabilidad'],'Competencia técnica'=>['programacion','programación','tecnolog','sistemas','software','datos','redes']
    ];
    $freq=array_fill_keys(array_keys($defs),0);
    foreach($values as $v){$n=' '.norm((string)$v).' ';foreach($defs as $label=>$terms){foreach($terms as $term){if(str_contains($n,norm($term))){$freq[$label]++;break;}}}}
    $freq=array_filter($freq,fn($v)=>$v>0);arsort($freq);return $freq;
}

function load_included_data(): array {$base=__DIR__.'/../datos';$out=[];foreach(['titulados','empleadores'] as $type){$file=$base.'/base_'.$type.'.csv';if(!is_file($file))throw new RuntimeException('No se encontró la base incluida: '.$type);$out[$type]=import_dataset($type,$file,basename($file));}return $out;}
