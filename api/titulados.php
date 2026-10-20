<?php
require_once __DIR__.'/lib/data.php';require_once __DIR__.'/lib/ui.php';
$b=dataset_bundle('titulados');page_start('Titulados','titulados.php','Antigüedad profesional, empleabilidad y formación continua');
if(!$b){empty_state('Importa la encuesta de titulados.');page_end();exit;}
$r=$b['rows'];$m=$b['map'];$n=count($r);
$vida=numeric_values(values_for($r,$m['vida_profesional']??null));$desemp=numeric_values(values_for($r,$m['desempleo']??null));
$sit=frequencies(values_for($r,$m['situacion_laboral']??null));$sector=frequencies(values_for($r,$m['sector']??null));$rubro=frequencies(values_for($r,$m['rubro']??null));
$areaVals=values_for($r,$m['area_trabajo']??null);$areaTrabajo=frequencies($areaVals,true);$cargoVals=values_for($r,$m['cargo']??null);$cargo=frequencies($cargoVals,true);
$medio=frequencies(values_for($r,$m['medio_empleo']??null));$tiempoPrimer=frequencies(values_for($r,$m['tiempo_primer_empleo']??null));$numEmpleos=frequencies(values_for($r,$m['num_empleos']??null));
$formComp=frequencies(values_for($r,$m['formacion_complementaria']??null));$nivel=frequencies(values_for($r,$m['nivel_posgrado']??null));$fin=frequencies(values_for($r,$m['financiamiento']??null));
$interes=frequencies(values_for($r,$m['interes_posgrado']??null));$area=frequencies(values_for($r,$m['area_interes']??null));$instFut=frequencies(values_for($r,$m['institucion_futura']??null));
$empleoOrg=count_match($sit,['trabajando un una organizacion','trabajando en una organizacion']);$empr=count_match($sit,['emprendimiento propio']);$desocup=count_match($sit,['no trabajo']);$activos=$empleoOrg+$empr;
$junior=0;$consolidado=0;foreach($vida as $v){if($v<=5)$junior++;else$consolidado++;}
$media=mean($vida);$med=median($vida);$de=stddev($vida);$mediaDes=mean($desemp);$siForm=count_match($formComp,['si']);$siInteres=count_match($interes,['si']);
?>
<div class="summary-strip"><div><span>Respuestas</span><b><?=$n?></b></div><div><span>En actividad laboral</span><b><?=pct($activos,$n)?></b></div><div><span>Actualmente sin trabajo</span><b><?=pct($desocup,$n)?></b></div><div><span>Interés en posgrado</span><b><?=pct($siInteres,array_sum($interes))?></b></div></div>

<h2 class="section-title numbered"><span>1</span>Antigüedad y empleabilidad</h2>
<div class="grid cols-4">
<?=stat_card('Promedio de experiencia profesional',$media!==null?number_format($media,2).' años':'N/D','Experiencia profesional promedio')?>
<?=stat_card('Experiencia mediana',$med!==null?number_format($med,2).' años':'N/D','El 50% de los titulados tiene '.number_format($med,2).' años o menos de experiencia profesional')?>
<?=stat_card('Desviación estándar',$de!==null?number_format($de,2).' años':'N/D','Nivel de dispersión de la experiencia profesional')?>
<?=stat_card('Desempleo promedio',$mediaDes!==null?number_format($mediaDes,2).' años':'N/D','Tiempo promedio acumulado sin empleo')?>
</div>
<div class="grid cols-2" style="margin-top:14px">
<div class="card"><h3 class="panel-title">Segmentación profesional</h3><div class="mini-grid"><div class="mini"><span>Junior</span><b><?=$junior?></b><small>Hasta 5 años de vida profesional</small></div><div class="mini"><span>Consolidado</span><b><?=$consolidado?></b><small>Más de 5 años</small></div></div></div>
<div class="card"><h3 class="panel-title">Situación laboral actual</h3><?=donut($sit)?></div>
<div class="card"><h3 class="panel-title">Sector de desempeño</h3><?=freq_table($sector)?></div>
<div class="card"><h3 class="panel-title">Tiempo hasta el primer empleo relacionado</h3><?=freq_table($tiempoPrimer,10)?></div>
</div>

<h2 class="section-title numbered"><span>2</span>Perfil laboral</h2>
<div class="grid cols-2">
<div class="card"><h3 class="panel-title">Rubros donde trabajan</h3><?=bars($rubro,10)?></div>
<div class="card"><h3 class="panel-title">Área de desempeño dentro de la organización</h3><?=bars($areaTrabajo,10,count($areaVals))?></div>
<div class="card"><h3 class="panel-title">Cargos desempeñados</h3><?=bars($cargo,10,count($cargoVals))?></div>
<div class="card"><h3 class="panel-title">Medio para obtener el empleo</h3><?=bars($medio,10)?></div>
<div class="card"><h3 class="panel-title">Cantidad de empleos desde la titulación</h3><?=freq_table($numEmpleos,10)?></div>
</div>

<h2 class="section-title numbered"><span>3</span>Formación continua e interés académico</h2>
<div class="grid cols-3">
<?=stat_card('Con formación complementaria',pct($siForm,array_sum($formComp)),$siForm.' respuestas afirmativas')?>
<?=stat_card('Interés en realizar posgrado',pct($siInteres,array_sum($interes)),$siInteres.' respuestas afirmativas')?>
<?=stat_card('Áreas de interés registradas',(string)count($area),'Preferencias temáticas')?>
</div>
<div class="grid cols-2" style="margin-top:14px">
<div class="card"><h3 class="panel-title">Nivel de formación complementaria alcanzado</h3><?=freq_table($nivel)?></div>
<div class="card"><h3 class="panel-title">Áreas de interés para futuros estudios</h3><?=freq_table($area)?></div>
<div class="card"><h3 class="panel-title">Fuente de financiamiento utilizada</h3><?=freq_table($fin)?></div>
<div class="card"><h3 class="panel-title">Institución preferida para futuros estudios</h3><?=freq_table($instFut)?></div>
</div>

<?php page_end();?>
