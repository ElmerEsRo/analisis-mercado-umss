<?php
function h($s): string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function pct(int|float $n,int|float $d): string{return $d>0?number_format($n*100/$d,1).'%':'0.0%';}
function page_start(string $title,string $active='',string $subtitle=''): void{
    $items=['index.php'=>['Inicio','⌂'],'importar.php'=>['Datos','⇧'],'titulados.php'=>['Titulados','◉'],'empleadores.php'=>['Empleadores','▦'],'cruces.php'=>['Cruces','↔'],'brechas.php'=>['Competencias','△'],'simulacion.php'=>['Simulación','▶'],'reportes.php'=>['Reportes','↓']];
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' · UMSS</title><link rel="stylesheet" href="assets/style.css"></head><body>';
    echo '<aside class="sidebar"><div class="brand"><div class="brand-mark logo-mark"><img src="assets/umss_logo_oficial.png" alt="Logo UMSS"></div><div><strong>Ingeniería en Sistemas</strong><small>Análisis de mercado</small></div></div><nav>';
    foreach($items as $href=>$it){$cls=$active===$href?'active':'';echo '<a class="'.$cls.'" href="'.$href.'"><span class="nav-icon">'.$it[1].'</span>'.h($it[0]).'</a>';}
    echo '</nav></aside>';
    echo '<main class="main"><header class="page-head"><div><h1>'.h($title).'</h1><p>'.h($subtitle?:'Ingeniería en Sistemas · Universidad Mayor de San Simón').'</p></div></header>';
}
function page_end(): void{echo '</main></body></html>';}
function empty_state(string $text): void{echo '<div class="empty"><strong>Faltan datos</strong><p>'.h($text).'</p><a class="btn" href="importar.php">Cargar datos</a></div>';}
function stat_card(string $label,string $value,string $sub=''): string{return '<div class="card stat"><span>'.h($label).'</span><strong>'.h($value).'</strong>'.($sub?'<small>'.h($sub).'</small>':'').'</div>';}
function action_card(string $title,string $text,string $href,string $button,string $status=''): string{return '<div class="card action-card"><div><h2>'.h($title).'</h2><p>'.h($text).'</p>'.($status?'<div class="data-status">'.h($status).'</div>':'').'</div><a class="btn" href="'.h($href).'">'.h($button).'</a></div>';}
function bars(array $freq,int $limit=8,?int $denominator=null,bool $showPct=true): string{
    if(!$freq)return '<p class="muted">Sin datos disponibles.</p>';$freq=array_slice($freq,0,$limit,true);$max=max($freq);$total=$denominator!==null?$denominator:array_sum($freq);$html='<div class="bars">';
    foreach($freq as $k=>$v){$w=$max?($v/$max*100):0;$p=$total?($v/$total*100):0;$suffix=$showPct?' <small>('.number_format($p,1).'%)</small>':'';$html.='<div class="bar-row"><div class="bar-label" title="'.h($k).'">'.h($k).'</div><div class="bar-track"><div class="bar-fill" style="width:'.number_format($w,1,'.','').'%"></div></div><b>'.$v.$suffix.'</b></div>';}
    return $html.'</div>';
}
function donut(array $freq): string{
    if(!$freq)return '<p class="muted">Sin datos disponibles.</p>';$freq=array_slice($freq,0,6,true);$total=array_sum($freq);$colors=['#2563eb','#0ea5e9','#10b981','#f59e0b','#8b5cf6','#ef4444'];$start=0;$stops=[];$legend='';$i=0;
    foreach($freq as $k=>$v){$p=$total?$v*100/$total:0;$end=$start+$p;$c=$colors[$i%count($colors)];$stops[]=$c.' '.$start.'% '.$end.'%';$legend.='<div><i style="background:'.$c.'"></i><span>'.h($k).'</span><b>'.number_format($p,1).'%</b></div>';$start=$end;$i++;}
    return '<div class="donut-wrap"><div class="donut" style="background:conic-gradient('.implode(',',$stops).')"><span>'.$total.'</span></div><div class="legend">'.$legend.'</div></div>';
}
function freq_table(array $freq,int $limit=20,?int $denominator=null): string{
    if(!$freq)return '<p class="muted">Sin datos disponibles.</p>';$freq=array_slice($freq,0,$limit,true);$total=$denominator!==null?$denominator:array_sum($freq);$html='<div class="table-wrap"><table><thead><tr><th>Categoría</th><th>Cantidad</th><th>Porcentaje</th></tr></thead><tbody>';
    foreach($freq as $k=>$v)$html.='<tr><td>'.h($k).'</td><td>'.$v.'</td><td>'.number_format($total?$v*100/$total:0,1).'%</td></tr>';return $html.'</tbody></table></div>';
}
function method_note(string $title,string $text): string{return '<div class="method-note"><b>'.h($title).'</b><span>'.h($text).'</span></div>';}
function info_box(string $title,string $text,string $type='info'): string{return '<div class="notice '.$type.'"><b>'.h($title).'</b><span>'.h($text).'</span></div>';}
function short_label(string $h): string{
    $n=norm($h);$map=['programa de formacion complementaria de mayor nivel'=>'Nivel de formación complementaria','fuente de financiamiento'=>'Fuente de financiamiento','situacion laboral actual'=>'Situación laboral actual','areas le interesaria realizar'=>'Área de interés','interesado en realizar estudios'=>'Interés en posgrado','sector corresponde'=>'Sector laboral','rubro de la organizacion'=>'Rubro','departamento de bolivia'=>'Departamento','genero:'=>'Género','tipo de organizacion'=>'Tipo de organización','tamano de la organizacion'=>'Tamaño de organización','nivel de formacion academica'=>'Formación requerida','medio convoca'=>'Medio de convocatoria','cargos desempenan'=>'Cargos','contratado ingenieros'=>'Contratación últimos 5 años','posibilidad de incorporar'=>'Contratación futura'];
    foreach($map as $needle=>$label)if(str_contains($n,$needle))return $label;return strlen(trim($h))>61?substr(trim($h),0,58).'...':trim($h);
}
function score_table(array $items,float $max=4): string{
    if(!$items)return '<p class="muted">Sin datos disponibles.</p>';$html='<div class="table-wrap"><table><thead><tr><th>Indicador</th><th>Promedio (de 4)</th><th>Desviación estándar</th><th>Cumplimiento</th><th>Brecha de valoración</th><th>Respuestas</th></tr></thead><tbody>';
    foreach($items as $v)$html.='<tr><td>'.h($v['label']).'</td><td>'.number_format($v['mean'],2).' / '.number_format($max,0).'</td><td>'.number_format($v['sd']??0,2).'</td><td>'.number_format($v['index'],1).'%</td><td>'.number_format($v['gap'],2).'</td><td>'.$v['n'].'</td></tr>';
    return $html.'</tbody></table></div>';
}


function score_table_1to5(array $items): string{
    if(!$items)return '<p class="muted">Sin datos disponibles.</p>';
    $html='<div class="table-wrap"><table><thead><tr><th>Indicador</th><th>Promedio (1 a 5)</th><th>Desviación estándar</th><th>Respuestas</th><th>Lectura</th></tr></thead><tbody>';
    foreach($items as $v){
        $m=(float)$v['mean'];
        $lectura=$m>=4?'Alta':($m>=3?'Media':'Baja');
        $html.='<tr><td>'.h($v['label']).'</td><td>'.number_format($m,2).'</td><td>'.number_format($v['sd']??0,2).'</td><td>'.$v['n'].'</td><td><b>'.$lectura.'</b></td></tr>';
    }
    return $html.'</tbody></table></div>';
}
