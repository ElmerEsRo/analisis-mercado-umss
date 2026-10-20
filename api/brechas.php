<?php
require_once __DIR__.'/lib/data.php';
require_once __DIR__.'/lib/ui.php';
$t=dataset_bundle('titulados');
$e=dataset_bundle('empleadores');
page_start('Competencias','brechas.php','Competencias, valoración y brechas formativas');
if(!$t&&!$e){empty_state('Importa las encuestas para analizar competencias.');page_end();exit;}

$titulados=[];
if($t){
    $defs=[
        'pertinencia'=>'Pertinencia entre trabajo y formación',
        'satisfaccion_formacion'=>'Satisfacción con la formación recibida',
        'concordancia_formacion'=>'Concordancia entre formación y requerimientos laborales'
    ];
    foreach($defs as $k=>$label){
        $s=likert_stats_1to5($t['rows'],$t['map'][$k]??null);
        if($s){$s['label']=$label;$titulados[]=$s;}
    }
}

$empleadores=[];
if($e){
    $defs=[
        ['desempeno profesional de los titulados de ingenieria de sistemas evidencia','Competencias laborales observadas'],
        ['demuestran valores y actitudes relacionados con la etica profesional','Ética y responsabilidad profesional'],
        ['perfil profesional declarado por la carrera de ingenieria de sistemas es coherente','Coherencia del perfil con el desempeño'],
        ['formacion proporcionada por la carrera de ingenieria de sistemas permite','Formación para el desempeño profesional']
    ];
    foreach($defs as[$needle,$label]){
        $h=find_header($e['headers'],[$needle]);
        $s=likert_stats_1to5($e['rows'],$h);
        if($s){$s['label']=$label;$empleadores[]=$s;}
    }
}

$habilidades=$e?values_for($e['rows'],$e['map']['habilidades']??null):[];
$herramientas=$e?values_for($e['rows'],$e['map']['herramientas']??null):[];
$tendencias=$e?values_for($e['rows'],$e['map']['tendencias']??null):[];
$hard=$e?thematic_frequencies(array_merge($habilidades,$herramientas,$tendencias),'hard'):[];
$soft=$e?thematic_frequencies($habilidades,'soft'):[];
$improve=$t?frequencies(values_for($t['rows'],$t['map']['aspectos_mejorar']??null),true):[];
$useful=$t?frequencies(values_for($t['rows'],$t['map']['aspectos_utiles']??null),true):[];
?>
<h2 class="section-title numbered"><span>1</span>Valoración de los titulados</h2>
<div class="card"><?=score_table_1to5($titulados)?></div>

<h2 class="section-title numbered"><span>2</span>Valoración de los empleadores</h2>
<div class="card"><?=score_table_1to5($empleadores)?></div>

<h2 class="section-title numbered"><span>3</span>Competencias requeridas por el mercado</h2>
<div class="grid cols-2">
    <div class="card"><h3 class="panel-title">Competencias técnicas (hard skills)</h3><?=bars($hard,8,count($e['rows']??[]))?></div>
    <div class="card"><h3 class="panel-title">Competencias transversales (soft skills)</h3><?=bars($soft,8,count($e['rows']??[]))?></div>
</div>

<h2 class="section-title numbered"><span>4</span>Diagnóstico de brechas formativas</h2>
<div class="grid cols-2">
    <div class="card"><h3 class="panel-title">Aspectos que requieren mejora según titulados</h3><?=bars($improve,8,count(values_for($t['rows']??[],$t['map']['aspectos_mejorar']??null)))?></div>
    <div class="card"><h3 class="panel-title">Fortalezas señaladas por titulados</h3><?=bars($useful,8,count(values_for($t['rows']??[],$t['map']['aspectos_utiles']??null)))?></div>
</div>
<?php page_end();?>
