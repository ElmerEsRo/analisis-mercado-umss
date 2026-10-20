<?php
require_once __DIR__.'/lib/data.php';
require_once __DIR__.'/lib/ui.php';

$t = dataset_bundle('titulados');
$e = dataset_bundle('empleadores');
page_start('Simulación','simulacion.php','Evaluación de escenarios académicos y laborales');

if (!$t || !$e) {
    empty_state('Importa los datos de titulados y empleadores para realizar la simulación.');
    page_end();
    exit;
}

$tm = $t['map'];
$em = $e['map'];
$tn = count($t['rows']);
$en = count($e['rows']);

// Situación observada: interés en posgrado
$inter = frequencies(values_for($t['rows'], $tm['interes_posgrado'] ?? null));
$validInt = array_sum($inter);
$yes = count_match($inter, ['si']);
$curInt = $validInt ? ($yes * 100 / $validInt) : 0;

// Situación observada: posibilidad de contratación futura
$fut = frequencies(values_for($e['rows'], $em['contratacion_futura'] ?? null));
$validHire = array_sum($fut);
$positive = count_match($fut, ['alta probabilidad']) + count_match($fut, ['dependiendo del crecimiento']);
$curHire = $validHire ? ($positive * 100 / $validHire) : 0;

// El usuario modifica la situación actual mediante una variación relativa.
$deltaInt = isset($_GET['delta_interes']) ? max(-100, min(100, (float)$_GET['delta_interes'])) : 0;
$deltaHire = isset($_GET['delta_contrata']) ? max(-100, min(100, (float)$_GET['delta_contrata'])) : 0;

$simInt = max(0, min(100, $curInt * (1 + $deltaInt / 100)));
$simHire = max(0, min(100, $curHire * (1 + $deltaHire / 100)));

$currentIntCount = (int)round($validInt * $curInt / 100);
$currentHireCount = (int)round($validHire * $curHire / 100);
$simIntCount = (int)round($validInt * $simInt / 100);
$simHireCount = (int)round($validHire * $simHire / 100);
$diffIntCount = $simIntCount - $currentIntCount;
$diffHireCount = $simHireCount - $currentHireCount;
$ppInt = $simInt - $curInt;
$ppHire = $simHire - $curHire;

function signed_num($n, $decimals = 1) {
    return ($n > 0 ? '+' : '').number_format($n, $decimals);
}
function scenario_text($diff, $nounSingular, $nounPlural) {
    $abs = abs((int)$diff);
    $noun = $abs === 1 ? $nounSingular : $nounPlural;
    if ($diff > 0) return "Se estiman {$abs} {$noun} adicionales.";
    if ($diff < 0) return "Se estiman {$abs} {$noun} menos.";
    return 'No se estima cambio respecto a la situación actual.';
}
?>

<div class="grid cols-2">
    <div class="card">
        <h3 class="panel-title big">Situación actual</h3>
        <p class="panel-sub">Valores calculados a partir de las respuestas cargadas.</p>
        <div class="mini-grid">
            <div class="mini">
                <span>Interés en realizar un posgrado</span>
                <b><?=number_format($curInt,1)?>%</b>
                <small><?=$currentIntCount?> de <?=$validInt?> titulados</small>
            </div>
            <div class="mini">
                <span>Organizaciones con posibilidad de contratar</span>
                <b><?=number_format($curHire,1)?>%</b>
                <small><?=$currentHireCount?> de <?=$validHire?> organizaciones</small>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="panel-title big">Probar un escenario</h3>
        <p class="panel-sub">Indica cuánto podría aumentar o disminuir cada indicador respecto a la situación actual.</p>
        <form method="get" class="filters">
            <div class="field">
                <label>Cambio en interés por posgrado (%)</label>
                <input type="number" name="delta_interes" min="-100" max="100" step="1" value="<?=htmlspecialchars((string)$deltaInt)?>">
            </div>
            <div class="field">
                <label>Cambio en posibilidad de contratación (%)</label>
                <input type="number" name="delta_contrata" min="-100" max="100" step="1" value="<?=htmlspecialchars((string)$deltaHire)?>">
            </div>
            <button class="btn">Simular</button>
        </form>
        <p class="caption">Ejemplo: escribe 10 para simular un aumento del 10%; escribe -10 para una disminución del 10%.</p>
    </div>
</div>

<h2 class="section-title">Resultado del escenario</h2>
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Indicador</th>
            <th>Situación actual</th>
            <th>Cambio aplicado</th>
            <th>Resultado estimado</th>
            <th>Impacto estimado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><b>Interés en posgrado</b></td>
            <td><?=number_format($curInt,1)?>%<br><small><?=$currentIntCount?> titulados</small></td>
            <td><?=signed_num($deltaInt,1)?>%</td>
            <td><b><?=number_format($simInt,1)?>%</b><br><small><?=$simIntCount?> de <?=$validInt?> titulados</small></td>
            <td><?=scenario_text($diffIntCount,'titulado interesado','titulados interesados')?></td>
        </tr>
        <tr>
            <td><b>Posibilidad de contratación</b></td>
            <td><?=number_format($curHire,1)?>%<br><small><?=$currentHireCount?> organizaciones</small></td>
            <td><?=signed_num($deltaHire,1)?>%</td>
            <td><b><?=number_format($simHire,1)?>%</b><br><small><?=$simHireCount?> de <?=$validHire?> organizaciones</small></td>
            <td><?=scenario_text($diffHireCount,'organización','organizaciones')?></td>
        </tr>
    </tbody>
</table>
</div>

<div class="grid cols-2" style="margin-top:14px">
    <div class="card">
        <h3 class="panel-title">Escenario académico</h3>
        <div class="mini-grid">
            <div class="mini"><span>Variación en puntos porcentuales</span><b><?=signed_num($ppInt,1)?></b></div>
            <div class="mini"><span>Cambio en titulados</span><b><?=($diffIntCount>0?'+':'').$diffIntCount?></b></div>
        </div>
        <p class="muted" style="margin:12px 0 0"><?=scenario_text($diffIntCount,'titulado interesado en posgrado','titulados interesados en posgrado')?></p>
    </div>
    <div class="card">
        <h3 class="panel-title">Escenario laboral</h3>
        <div class="mini-grid">
            <div class="mini"><span>Variación en puntos porcentuales</span><b><?=signed_num($ppHire,1)?></b></div>
            <div class="mini"><span>Cambio en organizaciones</span><b><?=($diffHireCount>0?'+':'').$diffHireCount?></b></div>
        </div>
        <p class="muted" style="margin:12px 0 0"><?=scenario_text($diffHireCount,'organización con posibilidad de contratar','organizaciones con posibilidad de contratar')?></p>
    </div>
</div>

<?php page_end(); ?>
