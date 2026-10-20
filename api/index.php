<?php
require_once __DIR__.'/lib/data.php';
require_once __DIR__.'/lib/ui.php';

try{
    $t=latest_dataset('titulados');
    $e=latest_dataset('empleadores');
    $dbok=true;
}catch(Throwable $x){
    $t=$e=null;
    $dbok=false;
    $dberr=$x->getMessage();
}

page_start('Inicio','index.php','Resumen del análisis de mercado');
if(!$dbok){
    echo '<div class="alert err">'.h($dberr).'</div>';
    page_end();
    exit;
}

$tCount=$t?(int)$t['rows_count']:0;
$eCount=$e?(int)$e['rows_count']:0;
$ready=$t&&$e;
$lastUpdate=date('d/m/Y');
?>

<div class="module-home-grid">
    <a class="home-module-card blue" href="titulados.php">
        <div class="home-module-icon">◉</div>
        <h2>Titulados</h2>
        <p>Analizar empleabilidad, experiencia profesional y formación continua.</p>
        <div class="home-module-meta"><?=$tCount?> respuestas cargadas</div>
        <span class="home-module-btn">Abrir <b>→</b></span>
    </a>

    <a class="home-module-card green" href="empleadores.php">
        <div class="home-module-icon">▦</div>
        <h2>Empleadores</h2>
        <p>Analizar demanda laboral, formación y competencias requeridas.</p>
        <div class="home-module-meta"><?=$eCount?> respuestas cargadas</div>
        <span class="home-module-btn">Abrir <b>→</b></span>
    </a>

    <a class="home-module-card purple" href="cruces.php">
        <div class="home-module-icon">↔</div>
        <h2>Cruces</h2>
        <p>Relacionar información de titulados y empleadores.</p>
        <div class="home-module-meta">Análisis estadístico</div>
        <span class="home-module-btn">Abrir <b>→</b></span>
    </a>

    <a class="home-module-card amber" href="reportes.php">
        <div class="home-module-icon">▥</div>
        <h2>Reportes</h2>
        <p>Generar resultados, tablas, gráficos y archivos de exportación.</p>
        <div class="home-module-meta">Resultados del análisis</div>
        <span class="home-module-btn">Abrir <b>→</b></span>
    </a>
</div>

<div class="home-status-bar">
    <div class="home-status-item"><span class="status-icon">▣</span><span>Última actualización: <b><?=$lastUpdate?></b></span></div>
    <div class="home-status-item <?=$ready?'ok':'pending'?>"><span class="status-dot"></span><span><?=$ready?'Datos cargados correctamente':'Faltan datos por cargar'?></span></div>
</div>

<?php page_end();?>
