<?php
require_once __DIR__.'/lib/data.php';require_once __DIR__.'/lib/ui.php';
$b=dataset_bundle('empleadores');page_start('Empleadores','empleadores.php','Oferta laboral y requerimientos del sector productivo');if(!$b){empty_state('Importa la encuesta de empleadores.');page_end();exit;}
$r=$b['rows'];$m=$b['map'];$n=count($r);$tipo=frequencies(values_for($r,$m['tipo_org']??null));$tam=frequencies(values_for($r,$m['tamano_org']??null));$rubro=frequencies(values_for($r,$m['rubro']??null));
$cont=frequencies(values_for($r,$m['contrato_5']??null));$fut=frequencies(values_for($r,$m['contratacion_futura']??null));$form=frequencies(values_for($r,$m['formacion_demandada']??null));
$cargoVals=values_for($r,$m['cargos']??null);$cargos=frequencies($cargoVals,true);$medios=frequencies(values_for($r,$m['medio_convoca']??null),true);
$tend=thematic_frequencies(values_for($r,$m['tendencias']??null),'tendencias');$herr=thematic_frequencies(values_for($r,$m['herramientas']??null),'herramientas');$hab=thematic_frequencies(values_for($r,$m['habilidades']??null),'habilidades');
$si=count_match($cont,['si']);$alta=count_match($fut,['alta probabilidad']);$posible=$alta+count_match($fut,['dependiendo del crecimiento']);
?>
<div class="summary-strip"><div><span>Organizaciones</span><b><?=$n?></b></div><div><span>Contrató Ing. de Sistemas</span><b><?=pct($si,array_sum($cont))?></b></div><div><span>Posibilidad de contratación futura</span><b><?=pct($posible,array_sum($fut))?></b></div><div><span>Rubros identificados</span><b><?=count($rubro)?></b></div></div>
<h2 class="section-title numbered"><span>1</span>Perfil de las organizaciones</h2>
<div class="grid cols-2"><div class="card"><h3 class="panel-title">Tipo de organización</h3><?=donut($tipo)?></div><div class="card"><h3 class="panel-title">Tamaño</h3><?=freq_table($tam)?></div><div class="card span-2"><h3 class="panel-title">Rubros principales</h3><?=bars($rubro,12)?></div></div>
<h2 class="section-title numbered"><span>2</span>Demanda laboral</h2>
<div class="grid cols-2"><div class="card"><h3 class="panel-title">Contratación en los últimos 5 años</h3><?=freq_table($cont)?></div><div class="card"><h3 class="panel-title">Contratación proyectada</h3><?=freq_table($fut)?></div><div class="card"><h3 class="panel-title">Formación requerida</h3><?=freq_table($form)?></div><div class="card"><h3 class="panel-title">Medios de convocatoria</h3><?=freq_table($medios)?></div><div class="card span-2"><h3 class="panel-title">Cargos desempeñados</h3><p class="panel-sub">Una organización puede seleccionar más de un cargo.</p><?=bars($cargos,12,count($cargoVals))?></div></div>
<h2 class="section-title numbered"><span>3</span>Áreas, tecnologías y competencias demandadas</h2>
<div class="grid cols-3"><div class="card"><h3 class="panel-title">Tendencias emergentes</h3><?=bars($tend,10,count(values_for($r,$m['tendencias']??null)))?></div><div class="card"><h3 class="panel-title">Herramientas tecnológicas</h3><?=bars($herr,10,count(values_for($r,$m['herramientas']??null)))?></div><div class="card"><h3 class="panel-title">Competencias mencionadas</h3><?=bars($hab,10,count(values_for($r,$m['habilidades']??null)))?></div></div>
<?php page_end();?>
