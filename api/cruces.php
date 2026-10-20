<?php
require_once __DIR__.'/lib/data.php';require_once __DIR__.'/lib/ui.php';
$b=dataset_bundle('titulados');page_start('Cruces estadísticos','cruces.php','Matrices de frecuencia y prueba de independencia Chi-cuadrado');if(!$b){empty_state('Importa la encuesta de titulados.');page_end();exit;}
$m=$b['map'];$labels=['nivel_posgrado'=>'Nivel de formación complementaria','financiamiento'=>'Fuente de financiamiento utilizada','area_interes'=>'Área de interés','interes_posgrado'=>'Interés en posgrado','situacion_laboral'=>'Situación laboral','sector'=>'Sector laboral','genero'=>'Género'];
$options=[];foreach($labels as $k=>$label)if(!empty($m[$k]))$options[$m[$k]]=$label;$keys=array_keys($options);
$defaultA=$m['nivel_posgrado']??($keys[0]??null);$defaultB=$m['financiamiento']??($keys[1]??null);$ha=$_GET['a']??$defaultA;$hb=$_GET['b']??$defaultB;if(!isset($options[$ha]))$ha=$defaultA;if(!isset($options[$hb]))$hb=$defaultB;
$result=null;if($ha&&$hb&&$ha!==$hb){[$ac,$bc,$mx,$nn]=contingency($b['rows'],$ha,$hb);$test=chi_square_test($mx,$ac,$bc);$result=[$ac,$bc,$mx,$nn,$test];}
?>
<div class="card"><form method="get" class="filters"><div class="field"><label>Variable de filas</label><select name="a"><?php foreach($options as $h=>$l):?><option value="<?=h($h)?>" <?=$h===$ha?'selected':''?>><?=h($l)?></option><?php endforeach;?></select></div><div class="field"><label>Variable de columnas</label><select name="b"><?php foreach($options as $h=>$l):?><option value="<?=h($h)?>" <?=$h===$hb?'selected':''?>><?=h($l)?></option><?php endforeach;?></select></div><button class="btn">Analizar</button></form></div>
<?php if($ha===$hb):?><div class="alert info" style="margin-top:14px">Selecciona dos variables diferentes.</div><?php endif;?>
<?php if($result):[$ac,$bc,$mx,$nn,$test]=$result;$p=$test['p'];?>
<h2 class="section-title numbered"><span>1</span>Prueba Chi-cuadrado de independencia</h2>
<div class="grid cols-4"><?=stat_card('Casos válidos',(string)$nn,'Respuestas incluidas')?><?=stat_card('Chi-cuadrado (χ²)',$test['chi2']!==null?number_format($test['chi2'],3):'N/D','Estadístico de la prueba')?><?=stat_card('Grados de libertad',(string)$test['df'],'Valor usado en la prueba')?><?=stat_card('Valor p',$p!==null?number_format($p,4):'N/D','Se compara con 0.05')?></div>
<?php if($p!==null):?>
<div class="interpretation" style="margin-top:14px"><b>Resultado</b><p><?php if($p<0.05):?>Existe una asociación estadísticamente significativa entre las variables analizadas (p = <?=number_format($p,4)?> &lt; 0.05).<?php else:?>No existe evidencia suficiente de una asociación estadísticamente significativa entre las variables analizadas (p = <?=number_format($p,4)?> ≥ 0.05).<?php endif;?></p></div>
<?php endif;?>
<h2 class="section-title numbered"><span>2</span>Matriz de frecuencia cruzada</h2>
<div class="card"><div class="cross-title"><b><?=h($options[$ha])?> × <?=h($options[$hb])?></b><a class="text-link" href="exportar.php?type=cruce&format=csv&a=<?=urlencode($ha)?>&b=<?=urlencode($hb)?>">Exportar matriz CSV</a></div><div class="table-wrap"><table><thead><tr><th><?=h($options[$ha])?></th><?php foreach($bc as $c):?><th><?=h($c)?></th><?php endforeach;?><th>Total</th></tr></thead><tbody><?php $cols=array_fill_keys($bc,0);$grand=0;foreach($ac as $a):$rt=0;?><tr><th><?=h($a)?></th><?php foreach($bc as $c):$v=$mx[$a][$c];$rt+=$v;$cols[$c]+=$v;$grand+=$v;?><td><?=$v?></td><?php endforeach;?><th><?=$rt?></th></tr><?php endforeach;?><tr><th>Total</th><?php foreach($bc as $c):?><th><?=$cols[$c]?></th><?php endforeach;?><th><?=$grand?></th></tr></tbody></table></div></div>
<?php if(!empty($test['warning'])):?><div class="interpretation"><b>Nota estadística</b><p>Algunas categorías tienen pocas respuestas, por lo que el resultado debe interpretarse con cautela.</p></div><?php endif;?>
<?php endif;?>
<?php page_end();?>
