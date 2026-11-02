<?php
require_once __DIR__.'/lib/ui.php';

page_start('Calidad y Validación','calidad.php','Análisis estadístico de tiempos de espera y validación de la calidad del servicio');

function parse_waiting_values(string $raw): array {
    $raw = str_replace(["\r\n", "\r", "\t", ';'], ["\n", "\n", ',', ','], trim($raw));
    $parts = preg_split('/[\n,]+/', $raw);
    $values = [];
    $invalid = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $part = str_replace(',', '.', $part);
        if (!is_numeric($part)) {
            $invalid[] = $part;
            continue;
        }
        $values[] = (float)$part;
    }
    return [$values, $invalid];
}

function stat_mean(array $v): ?float {
    return count($v) ? array_sum($v) / count($v) : null;
}
function stat_median(array $v): ?float {
    $n = count($v); if (!$n) return null;
    sort($v, SORT_NUMERIC);
    $m = intdiv($n, 2);
    return $n % 2 ? $v[$m] : (($v[$m-1] + $v[$m]) / 2);
}
function stat_mode(array $v): array {
    if (!$v) return [];
    $freq = [];
    foreach ($v as $x) {
        $k = rtrim(rtrim(number_format($x, 4, '.', ''), '0'), '.');
        $freq[$k] = ($freq[$k] ?? 0) + 1;
    }
    $max = max($freq);
    $modes = [];
    foreach ($freq as $k=>$c) if ($c === $max) $modes[] = (float)$k;
    return $max > 1 ? $modes : [];
}
function stat_variance_sample(array $v): ?float {
    $n = count($v); if ($n < 2) return null;
    $m = stat_mean($v); $s = 0.0;
    foreach ($v as $x) $s += ($x - $m) ** 2;
    return $s / ($n - 1);
}
function stat_sd_sample(array $v): ?float {
    $var = stat_variance_sample($v); return $var === null ? null : sqrt($var);
}
function stat_quartiles(array $v): array {
    $n = count($v); if (!$n) return [null,null,null];
    sort($v, SORT_NUMERIC);
    $q2 = stat_median($v);
    if ($n === 1) return [$v[0],$v[0],$v[0]];
    $mid = intdiv($n,2);
    $lower = array_slice($v, 0, $mid);
    $upper = array_slice($v, $n % 2 ? $mid+1 : $mid);
    return [stat_median($lower), $q2, stat_median($upper)];
}
function stat_percentile_nearest(array $v, float $p): ?float {
    $n = count($v); if (!$n) return null;
    sort($v, SORT_NUMERIC);
    $rank = max(1, min($n, (int)ceil($p * $n)));
    return $v[$rank-1];
}
function format_num(?float $x, int $d=2): string {
    if ($x === null || is_nan($x)) return '—';
    return number_format($x, $d, ',', '.');
}
function exact_frequencies(array $v): array {
    sort($v, SORT_NUMERIC); $f=[];
    foreach($v as $x){$k=rtrim(rtrim(number_format($x,4,'.',''),'0'),'.');$f[$k]=($f[$k]??0)+1;}
    return $f;
}
function grouped_intervals(array $v): array {
    $n=count($v); if(!$n)return [];
    $min=min($v);$max=max($v);
    if($min===$max)return [['from'=>$min,'to'=>$max,'mark'=>$min,'count'=>$n,'rel'=>1.0,'cum'=>$n]];
    $k=max(3,(int)ceil(1+3.322*log10($n)));
    $width=($max-$min)/$k;
    if($width<=0)$width=1;
    $intervals=[];$cum=0;
    for($i=0;$i<$k;$i++){
        $from=$min+$i*$width;
        $to=($i===$k-1)?$max:($min+($i+1)*$width);
        $count=0;
        foreach($v as $x){
            $inside=$i===$k-1?($x>=$from&&$x<=$to):($x>=$from&&$x<$to);
            if($inside)$count++;
        }
        $cum+=$count;
        $intervals[]=['from'=>$from,'to'=>$to,'mark'=>($from+$to)/2,'count'=>$count,'rel'=>$n?$count/$n:0,'cum'=>$cum];
    }
    return $intervals;
}
function svg_histogram(array $intervals): string {
    if(!$intervals)return '';
    $w=720;$h=280;$padL=50;$padB=38;$padT=20;$plotW=$w-$padL-18;$plotH=$h-$padT-$padB;
    $max=max(array_column($intervals,'count'));if($max<1)$max=1;
    $barW=$plotW/count($intervals);
    $svg='<svg class="quality-chart" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Histograma">';
    $svg.='<line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.($padT+$plotH).'" stroke="#94a3b8"/><line x1="'.$padL.'" y1="'.($padT+$plotH).'" x2="'.($padL+$plotW).'" y2="'.($padT+$plotH).'" stroke="#94a3b8"/>';
    foreach($intervals as $i=>$it){$bh=$it['count']/$max*$plotH;$x=$padL+$i*$barW+2;$y=$padT+$plotH-$bh;$bw=max(4,$barW-4);$svg.='<rect x="'.$x.'" y="'.$y.'" width="'.$bw.'" height="'.$bh.'" rx="3" fill="#60a5fa"/><text x="'.($x+$bw/2).'" y="'.($y-5).'" text-anchor="middle" font-size="11" fill="#334155">'.$it['count'].'</text><text x="'.($x+$bw/2).'" y="'.($padT+$plotH+18).'" text-anchor="middle" font-size="10" fill="#64748b">'.format_num($it['mark'],1).'</text>';}
    return $svg.'</svg>';
}
function svg_polygon(array $intervals): string {
    if(!$intervals)return '';$w=720;$h=280;$padL=50;$padB=38;$padT=20;$plotW=$w-$padL-18;$plotH=$h-$padT-$padB;$max=max(array_column($intervals,'count'));if($max<1)$max=1;$step=$plotW/max(1,count($intervals)-1);$pts=[];$svg='<svg class="quality-chart" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Polígono de frecuencias"><line x1="'.$padL.'" y1="'.($padT+$plotH).'" x2="'.($padL+$plotW).'" y2="'.($padT+$plotH).'" stroke="#94a3b8"/>';
    foreach($intervals as $i=>$it){$x=$padL+$i*$step;$y=$padT+$plotH-($it['count']/$max*$plotH);$pts[]=$x.','.$y;$svg.='<circle cx="'.$x.'" cy="'.$y.'" r="4" fill="#2563eb"/><text x="'.$x.'" y="'.($padT+$plotH+18).'" text-anchor="middle" font-size="10" fill="#64748b">'.format_num($it['mark'],1).'</text>';}
    $svg.='<polyline points="'.implode(' ',$pts).'" fill="none" stroke="#2563eb" stroke-width="3"/>';return $svg.'</svg>';
}
function svg_ogive(array $intervals,int $n): string {
    if(!$intervals)return '';$w=720;$h=280;$padL=50;$padB=38;$padT=20;$plotW=$w-$padL-18;$plotH=$h-$padT-$padB;$step=$plotW/max(1,count($intervals)-1);$pts=[];$svg='<svg class="quality-chart" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Ojiva"><line x1="'.$padL.'" y1="'.($padT+$plotH).'" x2="'.($padL+$plotW).'" y2="'.($padT+$plotH).'" stroke="#94a3b8"/>';
    foreach($intervals as $i=>$it){$x=$padL+$i*$step;$y=$padT+$plotH-($it['cum']/max(1,$n)*$plotH);$pts[]=$x.','.$y;$svg.='<circle cx="'.$x.'" cy="'.$y.'" r="4" fill="#0f766e"/><text x="'.$x.'" y="'.($padT+$plotH+18).'" text-anchor="middle" font-size="10" fill="#64748b">'.format_num($it['to'],1).'</text>';}
    $svg.='<polyline points="'.implode(' ',$pts).'" fill="none" stroke="#0f766e" stroke-width="3"/>';return $svg.'</svg>';
}
function svg_boxplot(array $v,float $q1,float $med,float $q3): string {
    if(!$v)return '';$min=min($v);$max=max($v);$iqr=$q3-$q1;$lowFence=$q1-1.5*$iqr;$highFence=$q3+1.5*$iqr;$nonOut=array_values(array_filter($v,fn($x)=>$x>=$lowFence&&$x<=$highFence));$whiskMin=$nonOut?min($nonOut):$min;$whiskMax=$nonOut?max($nonOut):$max;$out=array_values(array_filter($v,fn($x)=>$x<$lowFence||$x>$highFence));$w=720;$h=180;$left=55;$right=25;$y=80;$range=max(1e-9,$max-$min);$scale=fn($x)=>$left+($x-$min)/$range*($w-$left-$right);$svg='<svg class="quality-chart box" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Diagrama de caja">';$svg.='<line x1="'.$scale($whiskMin).'" y1="'.$y.'" x2="'.$scale($whiskMax).'" y2="'.$y.'" stroke="#334155" stroke-width="2"/><line x1="'.$scale($whiskMin).'" y1="60" x2="'.$scale($whiskMin).'" y2="100" stroke="#334155" stroke-width="2"/><line x1="'.$scale($whiskMax).'" y1="60" x2="'.$scale($whiskMax).'" y2="100" stroke="#334155" stroke-width="2"/><rect x="'.$scale($q1).'" y="48" width="'.max(2,$scale($q3)-$scale($q1)).'" height="64" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/><line x1="'.$scale($med).'" y1="48" x2="'.$scale($med).'" y2="112" stroke="#1d4ed8" stroke-width="3"/>';
    foreach($out as $x)$svg.='<circle cx="'.$scale($x).'" cy="'.$y.'" r="5" fill="#dc2626"/>';
    foreach([['Mín',$min],['Q1',$q1],['Me',$med],['Q3',$q3],['Máx',$max]] as [$lab,$x])$svg.='<text x="'.$scale($x).'" y="145" text-anchor="middle" font-size="10" fill="#64748b">'.$lab.' '.format_num($x,1).'</text>';
    return $svg.'</svg>';
}

$example = '4, 5, 5, 7, 8, 10, 12, 12, 15, 18, 20, 22, 5, 10, 8';
$raw = isset($_POST['tiempos']) ? trim((string)$_POST['tiempos']) : '';
$previousAvg = isset($_POST['promedio_anterior']) && $_POST['promedio_anterior'] !== '' ? (float)$_POST['promedio_anterior'] : 11.5;
$useExample = isset($_POST['usar_ejemplo']);
$qualityTarget = 9.0;
if ($useExample) $raw = $example;
[$values,$invalid] = $raw !== '' ? parse_waiting_values($raw) : [[],[]];
?>
<style>
.qv-intro{background:linear-gradient(135deg,#eff6ff,#fff);border:1px solid #bfdbfe}.qv-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px}.qv-step{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid #dbeafe;border-radius:12px;background:#fff}.qv-num{min-width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#2563eb;color:#fff;font-weight:700}.qv-step strong{display:block}.qv-step small{display:block;color:#64748b;margin-top:4px;line-height:1.35}.qv-main-result{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:14px}.qv-kpi{padding:16px;border:1px solid #dbe4ee;border-radius:14px;background:#fff}.qv-kpi span{display:block;color:#64748b;font-size:13px}.qv-kpi b{display:block;font-size:25px;margin:5px 0;color:#0f172a}.qv-kpi small{color:#64748b}.qv-explain{margin-top:12px;padding:12px 14px;border-radius:10px;background:#f8fafc;color:#334155;line-height:1.5}.qv-status-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.qv-status{border:1px solid #dbe4ee;border-radius:14px;padding:15px;background:#fff}.qv-status .top{display:flex;align-items:center;justify-content:space-between;gap:12px}.qv-pill{padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.qv-pill.good{background:#dcfce7;color:#166534}.qv-pill.warn{background:#fef3c7;color:#92400e}.qv-pill.bad{background:#fee2e2;color:#991b1b}.qv-status p{margin:8px 0 0;color:#475569;line-height:1.45}.qv-simple-list{display:grid;gap:9px}.qv-simple-row{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #e5e7eb}.qv-simple-row:last-child{border-bottom:0}.qv-simple-row span{color:#475569}.qv-simple-row b{text-align:right}.qv-details{margin-top:14px;border:1px solid #dbe4ee;border-radius:14px;background:#fff;overflow:hidden}.qv-details summary{cursor:pointer;padding:14px 16px;font-weight:700;background:#f8fafc}.qv-details .inside{padding:16px}.qv-chart-note{padding:10px 12px;border-radius:9px;background:#f8fafc;color:#475569;margin-top:8px;font-size:13px;line-height:1.4}.qv-sorted{padding:12px;border-radius:10px;background:#f8fafc;line-height:1.65;color:#334155}.qv-section{margin-top:20px}.qv-section h2{font-size:20px;margin-bottom:10px}.qv-section h2 span{display:inline-flex;width:28px;height:28px;border-radius:50%;background:#dbeafe;color:#1d4ed8;align-items:center;justify-content:center;margin-right:8px;font-size:14px}.qv-help{color:#64748b;margin:-4px 0 12px}.qv-big-answer{font-size:30px;font-weight:800;margin:3px 0}.qv-green{color:#15803d}.qv-red{color:#b91c1c}.qv-orange{color:#b45309}@media(max-width:900px){.qv-steps,.qv-main-result{grid-template-columns:1fr 1fr}.qv-status-grid{grid-template-columns:1fr}}@media(max-width:600px){.qv-steps,.qv-main-result{grid-template-columns:1fr}}
</style>

<div class="card qv-intro">
  <h3 class="panel-title big">Calidad y Validación — explicado paso a paso</h3>
  <p class="panel-sub">Esta sección responde una pregunta sencilla: <b>¿los tiempos de espera son aceptables y los datos sirven para evaluar el servicio?</b></p>
  <div class="qv-steps">
    <div class="qv-step"><div class="qv-num">1</div><div><strong>Ingresa los tiempos</strong><small>Un tiempo por cliente. La actividad espera 30 clientes.</small></div></div>
    <div class="qv-step"><div class="qv-num">2</div><div><strong>El sistema resume</strong><small>Te muestra promedio, valor central, tiempo más común y variación.</small></div></div>
    <div class="qv-step"><div class="qv-num">3</div><div><strong>Lee la conclusión</strong><small>Te dice si los datos son válidos y si se alcanza la meta de menos de 9 minutos.</small></div></div>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <h3 class="panel-title big">1. Ingresa la muestra</h3>
  <p class="panel-sub">Escribe los tiempos de espera en minutos, separados por comas o uno por línea.</p>
  <form method="post" class="quality-form">
    <div class="field span-2"><label>Tiempos de espera</label><textarea name="tiempos" rows="5" placeholder="Ejemplo: 4, 5, 7, 8, 10..."><?=h($raw)?></textarea></div>
    <div class="field"><label>Promedio anterior para comparar</label><input type="number" name="promedio_anterior" step="0.1" min="0" value="<?=h((string)$previousAvg)?>"><small style="color:#64748b">La guía usa 11,5 min como referencia.</small></div>
    <div class="quality-form-actions"><button class="btn" type="submit">Analizar</button><button class="btn secondary" type="submit" name="usar_ejemplo" value="1">Probar con los 15 datos de ejemplo</button></div>
  </form>
</div>

<?php if(!$values): ?>
<div class="empty" style="margin-top:16px"><strong>Aún no hay resultados</strong><p>Ingresa tiempos arriba y pulsa <b>Analizar</b>. No necesitas hacer ningún cálculo manual.</p></div>
<?php else:
$n=count($values);$mean=stat_mean($values);$median=stat_median($values);$modes=stat_mode($values);$var=stat_variance_sample($values);$sd=stat_sd_sample($values);$range=max($values)-min($values);$cv=($mean&&$sd!==null)?($sd/$mean*100):null;[$q1,$q2,$q3]=stat_quartiles($values);$p90=stat_percentile_nearest($values,.90);$freq=exact_frequencies($values);$intervals=grouped_intervals($values);$neg=count(array_filter($values,fn($x)=>$x<0));$over60=count(array_filter($values,fn($x)=>$x>60));$validQuality=$neg===0&&$over60===0&&!$invalid;$le15=count(array_filter($values,fn($x)=>$x<=15));$over15=$n-$le15;$diffPrev=$mean-$previousAvg;$iqr=$q3-$q1;$lowFence=$q1-1.5*$iqr;$highFence=$q3+1.5*$iqr;$outliers=array_values(array_filter($values,fn($x)=>$x<$lowFence||$x>$highFence));$meetsTarget=$mean<$qualityTarget;$sortedValues=$values;sort($sortedValues,SORT_NUMERIC);$missing=max(0,30-$n);$pct15=$n?($le15/$n*100):0;
$trend = $diffPrev < -0.01 ? 'bajó' : ($diffPrev > 0.01 ? 'subió' : 'se mantuvo igual');
?>

<div class="qv-section">
  <h2><span>2</span>Lo más importante de tu muestra</h2>
  <p class="qv-help">Primero mira estas cuatro cifras. Son las que mejor resumen lo que está pasando.</p>
  <div class="qv-main-result">
    <div class="qv-kpi"><span>Clientes analizados</span><b><?=$n?> / 30</b><small><?=$missing===0?'Muestra completa':'Faltan '.$missing.' para completar la muestra'?></small></div>
    <div class="qv-kpi"><span>Espera promedio</span><b><?=format_num($mean,2)?> min</b><small>En promedio, eso espera un cliente.</small></div>
    <div class="qv-kpi"><span>La mitad esperó hasta</span><b><?=format_num($median,2)?> min</b><small>Esto es la mediana.</small></div>
    <div class="qv-kpi"><span>Tiempo más repetido</span><b><?=$modes?h(implode(', ',array_map(fn($x)=>format_num($x,1),$modes))).' min':'No hay uno solo'?></b><small>Esto es la moda.</small></div>
  </div>
  <div class="qv-explain"><b>Lectura rápida:</b> <?=number_format($pct15,1)?>% de los clientes esperó 15 minutos o menos. El promedio actual es de <?=format_num($mean,2)?> minutos.</div>
</div>

<div class="qv-section">
  <h2><span>3</span>¿El servicio cumple?</h2>
  <p class="qv-help">Aquí no necesitas interpretar fórmulas: el sistema te da una respuesta directa.</p>
  <div class="qv-status-grid">
    <div class="qv-status"><div class="top"><strong>Meta de mejora: promedio menor a 9 min</strong><span class="qv-pill <?=$meetsTarget?'good':'bad'?>"><?=$meetsTarget?'CUMPLE':'NO CUMPLE'?></span></div><div class="qv-big-answer <?=$meetsTarget?'qv-green':'qv-red'?>"><?=format_num($mean,2)?> min</div><p><?=$meetsTarget?'La muestra ya está por debajo de la meta establecida.':'El promedio todavía está por encima de la meta. Faltan '.format_num($mean-$qualityTarget,2).' min para llegar al límite de 9 min.'?></p></div>
    <div class="qv-status"><div class="top"><strong>Comparación con la medición anterior</strong><span class="qv-pill <?=abs($diffPrev)<0.01?'good':($diffPrev<0?'good':'warn')?>"><?=strtoupper($trend)?></span></div><div class="qv-big-answer <?=($diffPrev<=0?'qv-green':'qv-orange')?>"><?=($diffPrev>0?'+':'').format_num($diffPrev,2)?> min</div><p>Antes: <?=format_num($previousAvg,2)?> min. Ahora: <?=format_num($mean,2)?> min. El tiempo promedio <?=$trend?> <?=format_num(abs($diffPrev),2)?> min.</p></div>
    <div class="qv-status"><div class="top"><strong>¿Los datos son válidos?</strong><span class="qv-pill <?=$validQuality?'good':'bad'?>"><?=$validQuality?'SÍ':'REVISAR'?></span></div><p><?=$validQuality?'No se encontraron tiempos negativos, mayores a 60 min ni textos inválidos.':'Hay valores que deben corregirse antes de considerar válida la muestra.'?></p><div class="qv-simple-list"><div class="qv-simple-row"><span>Negativos</span><b><?=$neg?></b></div><div class="qv-simple-row"><span>Mayores a 60 min</span><b><?=$over60?></b></div><div class="qv-simple-row"><span>No numéricos</span><b><?=count($invalid)?></b></div></div></div>
    <div class="qv-status"><div class="top"><strong>¿La muestra está completa?</strong><span class="qv-pill <?=$n===30?'good':'warn'?>"><?=$n===30?'SÍ':'TODAVÍA NO'?></span></div><div class="qv-big-answer <?=$n===30?'qv-green':'qv-orange'?>"><?=$n?> / 30</div><p><?=$n===30?'Se alcanzó el tamaño de muestra solicitado.':'La actividad pide 30 clientes. Puedes usar los 15 datos de ejemplo para probar, pero para la muestra completa faltan '.$missing.'.'?></p></div>
  </div>
</div>

<div class="qv-section">
  <h2><span>4</span>¿Qué tan diferentes son los tiempos?</h2>
  <p class="qv-help">Esta parte explica la variación sin obligarte a interpretar términos estadísticos.</p>
  <div class="grid cols-2 quality-two">
    <div class="card"><h3 class="panel-title">Variación entre clientes</h3><div class="qv-simple-list"><div class="qv-simple-row"><span>Tiempo más corto</span><b><?=format_num(min($values),1)?> min</b></div><div class="qv-simple-row"><span>Tiempo más largo</span><b><?=format_num(max($values),1)?> min</b></div><div class="qv-simple-row"><span>Diferencia entre ambos</span><b><?=format_num($range,1)?> min</b></div><div class="qv-simple-row"><span>Desviación estándar</span><b><?=format_num($sd,2)?> min</b></div><div class="qv-simple-row"><span>Coeficiente de variación</span><b><?=format_num($cv,1)?>%</b></div></div><div class="qv-explain"><?=$cv!==null&&$cv>30?'Los tiempos cambian bastante de un cliente a otro; el servicio no es muy uniforme.':'Los tiempos muestran una variación moderada o baja entre clientes.'?></div></div>
    <div class="card"><h3 class="panel-title">¿Dónde se concentra la mayoría?</h3><div class="qv-simple-list"><div class="qv-simple-row"><span>25% esperó hasta (Q1)</span><b><?=format_num($q1,2)?> min</b></div><div class="qv-simple-row"><span>50% esperó hasta (Q2)</span><b><?=format_num($q2,2)?> min</b></div><div class="qv-simple-row"><span>75% esperó hasta (Q3)</span><b><?=format_num($q3,2)?> min</b></div><div class="qv-simple-row"><span>90% esperó hasta (P90)</span><b><?=format_num($p90,2)?> min</b></div></div><div class="qv-explain">Ejemplo: P90 = <?=format_num($p90,1)?> min significa que aproximadamente 9 de cada 10 clientes esperaron <?=format_num($p90,1)?> minutos o menos.</div></div>
  </div>
</div>

<div class="qv-section">
  <h2><span>5</span>Gráficos — qué significa cada uno</h2>
  <p class="qv-help">Los cuatro gráficos se mantienen porque los pide la actividad, pero cada uno incluye una explicación sencilla.</p>
  <div class="grid cols-2 quality-charts">
    <div class="card"><h3 class="panel-title">Histograma</h3><p class="panel-sub">Muestra en qué rangos se concentran más clientes.</p><?=svg_histogram($intervals)?><div class="qv-chart-note"><b>Cómo leerlo:</b> una barra más alta significa que más clientes tuvieron tiempos dentro de ese rango.</div></div>
    <div class="card"><h3 class="panel-title">Polígono de frecuencias</h3><p class="panel-sub">Muestra cómo sube o baja la cantidad de clientes según el tiempo.</p><?=svg_polygon($intervals)?><div class="qv-chart-note"><b>Cómo leerlo:</b> los puntos altos indican los rangos de espera más frecuentes.</div></div>
    <div class="card"><h3 class="panel-title">Diagrama de caja</h3><p class="panel-sub">Resume el centro de los datos y ayuda a detectar tiempos inusuales.</p><?=svg_boxplot($values,$q1,$q2,$q3)?><div class="qv-chart-note"><b>Cómo leerlo:</b> los puntos separados de la caja pueden ser valores atípicos. Detectados: <?=count($outliers)?>.</div></div>
    <div class="card"><h3 class="panel-title">Ojiva</h3><p class="panel-sub">Muestra cuántos clientes se van acumulando hasta cada tiempo.</p><?=svg_ogive($intervals,$n)?><div class="qv-chart-note"><b>Cómo leerlo:</b> sirve para responder “¿cuántos clientes esperaron hasta cierto número de minutos?”.</div></div>
  </div>
</div>

<div class="qv-section">
  <h2><span>6</span>Conclusión que puedes explicar</h2>
  <div class="card">
    <p style="font-size:16px;line-height:1.7;margin:0"><?php
      echo 'Se analizaron <b>'.$n.'</b> tiempos de espera. El promedio fue de <b>'.format_num($mean,2).' min</b> y la mitad de los clientes esperó <b>'.format_num($median,2).' min o menos</b>. ';
      echo '<b>'.number_format($pct15,1).'%</b> esperó 15 minutos o menos. ';
      if($cv!==null) echo $cv>30 ? 'Los tiempos presentan <b>alta variación</b>, por lo que la atención no es uniforme entre clientes. ' : 'La variación de los tiempos es moderada o baja. ';
      if($outliers) echo 'Se detectaron <b>'.count($outliers).' posible(s) valor(es) atípico(s)</b>. ';
      echo $meetsTarget ? 'La muestra <b>cumple</b> la meta de mantener el promedio por debajo de 9 minutos.' : 'La muestra <b>todavía no cumple</b> la meta de reducir el promedio a menos de 9 minutos.';
    ?></p>
    <div class="qv-explain"><b>Recomendación:</b> <?=$meetsTarget?'Mantener las acciones aplicadas y volver a medir periódicamente para comprobar que el resultado se sostiene.':'Reforzar la atención en horas de mayor demanda y volver a tomar una muestra de 30 clientes después de aplicar la mejora.'?></div>
  </div>
</div>

<details class="qv-details">
  <summary>Ver datos ordenados y tablas estadísticas requeridas en la actividad</summary>
  <div class="inside">
    <div class="qv-sorted"><b>Datos ordenados:</b> <?=h(implode(', ',array_map(fn($x)=>format_num($x,1),$sortedValues)))?></div>
    <div class="grid cols-2 quality-two" style="margin-top:14px">
      <div><h3 class="panel-title">Tabla de frecuencias</h3><div class="table-wrap"><table><thead><tr><th>Tiempo</th><th>Veces</th><th>%</th><th>Acumulado</th></tr></thead><tbody><?php $cum=0;foreach($freq as $x=>$c):$cum+=$c;?><tr><td><?=h($x)?></td><td><?=$c?></td><td><?=number_format($c/$n*100,1)?>%</td><td><?=$cum?></td></tr><?php endforeach;?></tbody></table></div></div>
      <div><h3 class="panel-title">Tabla por intervalos</h3><div class="table-wrap"><table><thead><tr><th>Intervalo</th><th>Marca</th><th>Veces</th><th>%</th></tr></thead><tbody><?php foreach($intervals as $it):?><tr><td>[<?=format_num($it['from'],1)?> – <?=format_num($it['to'],1)?>]</td><td><?=format_num($it['mark'],1)?></td><td><?=$it['count']?></td><td><?=number_format($it['rel']*100,1)?>%</td></tr><?php endforeach;?></tbody></table></div></div>
    </div>
    <div class="card" style="margin-top:14px"><h3 class="panel-title">Valores técnicos completos</h3><div class="qv-simple-list"><div class="qv-simple-row"><span>Media</span><b><?=format_num($mean,2)?></b></div><div class="qv-simple-row"><span>Mediana</span><b><?=format_num($median,2)?></b></div><div class="qv-simple-row"><span>Moda</span><b><?=$modes?h(implode(', ',array_map(fn($x)=>format_num($x,1),$modes))):'Sin moda'?></b></div><div class="qv-simple-row"><span>Rango</span><b><?=format_num($range,2)?></b></div><div class="qv-simple-row"><span>Varianza muestral</span><b><?=format_num($var,2)?></b></div><div class="qv-simple-row"><span>Desviación estándar</span><b><?=format_num($sd,2)?></b></div><div class="qv-simple-row"><span>Coeficiente de variación</span><b><?=format_num($cv,1)?>%</b></div><div class="qv-simple-row"><span>Q1 / Q2 / Q3</span><b><?=format_num($q1,2)?> / <?=format_num($q2,2)?> / <?=format_num($q3,2)?></b></div><div class="qv-simple-row"><span>P90</span><b><?=format_num($p90,2)?></b></div></div></div>
  </div>
</details>

<?php endif; ?>
<?php page_end(); ?>
