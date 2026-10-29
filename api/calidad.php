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
if ($useExample) $raw = $example;
[$values,$invalid] = $raw !== '' ? parse_waiting_values($raw) : [[],[]];

?>
<div class="card quality-input-card">
    <div class="cross-title">
        <div>
            <h3 class="panel-title big">Datos de tiempos de espera</h3>
            <p class="panel-sub">Ingresa los tiempos en minutos separados por comas o por líneas. La actividad plantea una muestra objetivo de 30 clientes.</p>
        </div>
        <span class="badge gray">Variable cuantitativa</span>
    </div>
    <form method="post" class="quality-form">
        <div class="field span-2">
            <label>Tiempos de espera (minutos)</label>
            <textarea name="tiempos" rows="5" placeholder="Ejemplo: 4, 5, 7, 8, 10..."><?=h($raw)?></textarea>
        </div>
        <div class="field">
            <label>Promedio de referencia anterior (min)</label>
            <input type="number" name="promedio_anterior" step="0.1" min="0" value="<?=h((string)$previousAvg)?>">
        </div>
        <div class="quality-form-actions">
            <button class="btn" type="submit">Analizar muestra</button>
            <button class="btn secondary" type="submit" name="usar_ejemplo" value="1">Cargar ejemplo de 15 datos</button>
        </div>
    </form>
    <p class="caption">El ejemplo corresponde a los 15 valores mostrados en la guía. Para la validación completa deben cargarse los 30 tiempos reales de la muestra.</p>
</div>

<?php if(!$values): ?>
    <div class="empty" style="margin-top:16px"><strong>Ingresa una muestra para comenzar</strong><p>El módulo calculará medidas estadísticas, tablas de frecuencia, gráficos y validaciones automáticamente.</p></div>
<?php else:
    $n=count($values);$mean=stat_mean($values);$median=stat_median($values);$modes=stat_mode($values);$var=stat_variance_sample($values);$sd=stat_sd_sample($values);$range=max($values)-min($values);$cv=($mean&&$sd!==null)?($sd/$mean*100):null;[$q1,$q2,$q3]=stat_quartiles($values);$p90=stat_percentile_nearest($values,.90);$freq=exact_frequencies($values);$intervals=grouped_intervals($values);$neg=count(array_filter($values,fn($x)=>$x<0));$over60=count(array_filter($values,fn($x)=>$x>60));$validQuality=$neg===0&&$over60===0&&!$invalid;$le15=count(array_filter($values,fn($x)=>$x<=15));$over15=$n-$le15;$diffPrev=$mean-$previousAvg;$iqr=$q3-$q1;$lowFence=$q1-1.5*$iqr;$highFence=$q3+1.5*$iqr;$outliers=array_values(array_filter($values,fn($x)=>$x<$lowFence||$x>$highFence));
?>

<div class="summary-strip quality-summary">
    <div><span>Tamaño de muestra</span><b><?=$n?></b><small><?=$n===30?'Muestra objetivo completa':'Objetivo: 30 clientes'?></small></div>
    <div><span>Media</span><b><?=format_num($mean,2)?> min</b><small>Tiempo promedio</small></div>
    <div><span>Mediana</span><b><?=format_num($median,2)?> min</b><small>50% de los casos</small></div>
    <div><span>Desviación estándar</span><b><?=format_num($sd,2)?> min</b><small>Dispersión de la muestra</small></div>
</div>

<?php if($n!==30): ?>
<div class="alert info">La actividad establece una muestra de 30 clientes. Actualmente se analizaron <b><?=$n?></b> registros.</div>
<?php endif; ?>
<?php if($invalid): ?><div class="alert err">Se omitieron valores no numéricos: <?=h(implode(', ',$invalid))?></div><?php endif; ?>

<h2 class="section-title numbered"><span>1</span>Tabla de frecuencias</h2>
<div class="grid cols-2 quality-two">
    <div class="card">
        <h3 class="panel-title">Frecuencia por valor</h3>
        <div class="table-wrap"><table><thead><tr><th>Tiempo (xi)</th><th>fi</th><th>hi</th><th>Fi</th></tr></thead><tbody>
        <?php $cum=0;foreach($freq as $x=>$c):$cum+=$c;?><tr><td><?=h($x)?></td><td><?=$c?></td><td><?=number_format($c/$n*100,1)?>%</td><td><?=$cum?></td></tr><?php endforeach;?>
        </tbody><tfoot><tr><th>Total</th><th><?=$n?></th><th>100%</th><th><?=$n?></th></tr></tfoot></table></div>
    </div>
    <div class="card">
        <h3 class="panel-title">Distribución por intervalos</h3>
        <div class="table-wrap"><table><thead><tr><th>Intervalo</th><th>Marca</th><th>fi</th><th>hi</th></tr></thead><tbody>
        <?php foreach($intervals as $it):?><tr><td>[<?=format_num($it['from'],1)?> – <?=format_num($it['to'],1)?>]</td><td><?=format_num($it['mark'],1)?></td><td><?=$it['count']?></td><td><?=number_format($it['rel']*100,1)?>%</td></tr><?php endforeach;?>
        </tbody></table></div>
    </div>
</div>

<h2 class="section-title numbered"><span>2</span>Medidas estadísticas</h2>
<div class="grid cols-3 quality-stats">
    <div class="card"><h3 class="panel-title">Tendencia central</h3><div class="quality-list"><div><span>Media</span><b><?=format_num($mean,2)?> min</b></div><div><span>Mediana</span><b><?=format_num($median,2)?> min</b></div><div><span>Moda</span><b><?=$modes?h(implode(', ',array_map(fn($x)=>format_num($x,1),$modes))).' min':'Sin moda'?></b></div></div></div>
    <div class="card"><h3 class="panel-title">Dispersión</h3><div class="quality-list"><div><span>Rango</span><b><?=format_num($range,2)?> min</b></div><div><span>Varianza muestral</span><b><?=format_num($var,2)?></b></div><div><span>Coeficiente de variación</span><b><?=format_num($cv,1)?>%</b></div></div></div>
    <div class="card"><h3 class="panel-title">Posición</h3><div class="quality-list"><div><span>Q1</span><b><?=format_num($q1,2)?> min</b></div><div><span>Q2</span><b><?=format_num($q2,2)?> min</b></div><div><span>Q3 / P90</span><b><?=format_num($q3,2)?> / <?=format_num($p90,2)?> min</b></div></div></div>
</div>

<h2 class="section-title numbered"><span>3</span>Gráficos</h2>
<div class="grid cols-2 quality-charts">
    <div class="card"><h3 class="panel-title">Histograma</h3><p class="panel-sub">Frecuencia de tiempos agrupados por intervalos.</p><?=svg_histogram($intervals)?></div>
    <div class="card"><h3 class="panel-title">Polígono de frecuencias</h3><p class="panel-sub">Tendencia de la distribución de frecuencias.</p><?=svg_polygon($intervals)?></div>
    <div class="card"><h3 class="panel-title">Diagrama de caja</h3><p class="panel-sub">Cuartiles, mediana y posibles valores atípicos.</p><?=svg_boxplot($values,$q1,$q2,$q3)?></div>
    <div class="card"><h3 class="panel-title">Ojiva</h3><p class="panel-sub">Frecuencia acumulada por intervalo.</p><?=svg_ogive($intervals,$n)?></div>
</div>

<h2 class="section-title numbered"><span>4</span>Análisis y validación</h2>
<div class="grid cols-2 quality-validation">
    <div class="card">
        <h3 class="panel-title">Validación de datos</h3>
        <div class="quality-checks">
            <div class="<?=$neg===0?'ok':'bad'?>"><b><?=$neg===0?'✓':'!'?></b><span>Valores negativos</span><strong><?=$neg?></strong></div>
            <div class="<?=$over60===0?'ok':'bad'?>"><b><?=$over60===0?'✓':'!'?></b><span>Tiempos mayores a 60 min</span><strong><?=$over60?></strong></div>
            <div class="<?=!$invalid?'ok':'bad'?>"><b><?=!$invalid?'✓':'!'?></b><span>Valores no numéricos</span><strong><?=count($invalid)?></strong></div>
            <div class="<?=$n===30?'ok':'warn'?>"><b><?=$n===30?'✓':'!'?></b><span>Tamaño de muestra esperado</span><strong><?=$n?> / 30</strong></div>
        </div>
    </div>
    <div class="card">
        <h3 class="panel-title">Lectura de calidad</h3>
        <div class="quality-list">
            <div><span>Esperó 15 min o menos</span><b><?=number_format($le15/$n*100,1)?>%</b></div>
            <div><span>Esperó más de 15 min</span><b><?=number_format($over15/$n*100,1)?>%</b></div>
            <div><span>Valores atípicos por IQR</span><b><?=count($outliers)?></b></div>
            <div><span>Diferencia vs. referencia</span><b><?=($diffPrev>0?'+':'').format_num($diffPrev,2)?> min</b></div>
        </div>
    </div>
</div>

<div class="card quality-conclusion" style="margin-top:14px">
    <h3 class="panel-title big">Resultado de la validación</h3>
    <p><?php
        $shape = $mean > $median + 0.01 ? 'La distribución presenta tendencia hacia la derecha, ya que la media es mayor que la mediana.' : ($mean < $median - 0.01 ? 'La distribución presenta tendencia hacia la izquierda, ya que la media es menor que la mediana.' : 'La media y la mediana son similares, por lo que la distribución no muestra una asimetría marcada.');
        echo h($shape.' '.number_format($le15/$n*100,1).'% de los clientes esperó 15 minutos o menos. ');
        if($cv!==null) echo h('El coeficiente de variación es '.format_num($cv,1).'%, lo que '.($cv>30?'indica una variabilidad elevada en los tiempos de espera.':'muestra una variabilidad moderada o baja en los tiempos de espera.').' ');
        if($outliers) echo h('Se identificaron '.count($outliers).' posible(s) valor(es) atípico(s): '.implode(', ',array_map(fn($x)=>format_num($x,1),$outliers)).' min. ');
        echo $validQuality ? 'Los registros analizados cumplen las reglas básicas de validación de rango.' : 'Se detectaron registros que requieren revisión antes de validar la muestra.';
    ?></p>
</div>

<?php endif; ?>
<?php page_end(); ?>
