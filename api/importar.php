<?php
require_once __DIR__.'/lib/data.php';
require_once __DIR__.'/lib/ui.php';

$msg='';$err='';
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $type=$_POST['type']??'';
        $action=$_POST['action']??'';

        if(!in_array($type,['titulados','empleadores'],true))
            throw new RuntimeException('Tipo de encuesta inválido.');

        if($action==='clear'){
            clear_dataset($type);
            $msg=$type==='titulados'
                ? 'Se eliminaron todos los registros de Titulados.'
                : 'Se eliminaron todos los registros de Empleadores.';
        }elseif(in_array($action,['append','replace'],true)){
            if(!isset($_FILES['archivo'])||$_FILES['archivo']['error']!==UPLOAD_ERR_OK)
                throw new RuntimeException('Selecciona un archivo válido.');

            $name=$_FILES['archivo']['name'];
            $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
            if(!in_array($ext,['xlsx','csv'],true))
                throw new RuntimeException('Formato permitido: XLSX o CSV.');

            if($action==='append'){
                $r=append_dataset($type,$_FILES['archivo']['tmp_name'],$name);
                $msg='Se agregaron '.$r['added'].' registros. Total actual: '.$r['total'].'.';
            }else{
                replace_dataset($type,$_FILES['archivo']['tmp_name'],$name);
                $ds=latest_dataset($type);
                $msg='Nueva base cargada: '.$ds['rows_count'].' registros.';
            }
        }else{
            throw new RuntimeException('Acción inválida.');
        }
    }

    $t=latest_dataset('titulados');
    $e=latest_dataset('empleadores');
}catch(Throwable $ex){
    $err=$ex->getMessage();
    try{$t=latest_dataset('titulados');$e=latest_dataset('empleadores');}
    catch(Throwable $ignore){$t=$e=null;}
}

page_start('Datos','importar.php','Administrar encuestas de Titulados y Empleadores');
if($msg)echo '<div class="alert ok">'.h($msg).'</div>';
if($err)echo '<div class="alert err">'.h($err).'</div>';

function dataset_status(?array $ds): string {
    if(!$ds || (int)$ds['rows_count']===0){
        return '<div class="loaded empty-data"><b>Base vacía</b><span>0 registros</span><small>Puede cargar una nueva base o agregar registros.</small></div>';
    }
    return '<div class="loaded"><b>✓ Datos disponibles</b><span>'.(int)$ds['rows_count'].' registros</span><small>'.h($ds['filename']).'</small></div>';
}
?>
<div class="grid cols-2">
  <div class="card">
    <h2 class="panel-title big">Encuesta de Titulados</h2>
    <p class="panel-sub">Archivo XLSX o CSV exportado de Microsoft Forms.</p>

    <form method="post" enctype="multipart/form-data" class="upload data-actions">
      <input type="hidden" name="type" value="titulados">
      <input type="file" name="archivo" accept=".xlsx,.csv" required>

      <div class="action-buttons">
        <button class="btn" type="submit" name="action" value="append">Agregar registros</button>
        <button class="btn secondary" type="submit" name="action" value="replace">Cargar nueva base</button>
      </div>

      <div class="action-help">
        <span><b>Agregar registros:</b> conserva los actuales y suma los nuevos.</span>
        <span><b>Cargar nueva base:</b> reemplaza todos los datos actuales por el archivo seleccionado.</span>
      </div>
    </form>

    <form method="post" class="clear-form" onsubmit="return confirm('¿Seguro que desea eliminar todos los datos de Titulados? Esta acción no se puede deshacer.');">
      <input type="hidden" name="type" value="titulados">
      <input type="hidden" name="action" value="clear">
      <button class="btn danger" type="submit">Vaciar base</button>
      <span>Elimina todos los registros cargados.</span>
    </form>

    <?=dataset_status($t)?>
  </div>

  <div class="card">
    <h2 class="panel-title big">Encuesta de Empleadores</h2>
    <p class="panel-sub">Archivo XLSX o CSV exportado de Google Forms.</p>

    <form method="post" enctype="multipart/form-data" class="upload data-actions">
      <input type="hidden" name="type" value="empleadores">
      <input type="file" name="archivo" accept=".xlsx,.csv" required>

      <div class="action-buttons">
        <button class="btn" type="submit" name="action" value="append">Agregar registros</button>
        <button class="btn secondary" type="submit" name="action" value="replace">Cargar nueva base</button>
      </div>

      <div class="action-help">
        <span><b>Agregar registros:</b> conserva los actuales y suma los nuevos.</span>
        <span><b>Cargar nueva base:</b> reemplaza todos los datos actuales por el archivo seleccionado.</span>
      </div>
    </form>

    <form method="post" class="clear-form" onsubmit="return confirm('¿Seguro que desea eliminar todos los datos de Empleadores? Esta acción no se puede deshacer.');">
      <input type="hidden" name="type" value="empleadores">
      <input type="hidden" name="action" value="clear">
      <button class="btn danger" type="submit">Vaciar base</button>
      <span>Elimina todos los registros cargados.</span>
    </form>

    <?=dataset_status($e)?>
  </div>
</div>
<?php page_end();?>
