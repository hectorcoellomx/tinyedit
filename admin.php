<?php

require_once 'model.php';

$itemModel = new Item();

// Variables para mensajes
$message = null;
$messageType = null; // 'success', 'error', 'warning'

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shortname'])) {
	// Validar campos requeridos
	$errors = [];
	
	if (empty($_POST['shortname'])) {
		$errors[] = 'El shortname es obligatorio';
	}
	
	// Validar que shortname no tenga espacios ni caracteres especiales
	if (!empty($_POST['shortname']) && !preg_match('/^[a-z0-9_\-]+$/i', $_POST['shortname'])) {
		$errors[] = 'El shortname solo puede contener letras, números, guiones y guiones bajos';
	}
	
	if (!empty($errors)) {
		$message = 'Error al guardar el item:<br>' . implode('<br>', $errors);
		$messageType = 'error';
	} else {

		// Preparar datos para guardar
		$parent = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
		$data = [
			'parent_id' => $parent,
			'shortname' => $_POST['shortname'],
			'title' => $_POST['title'] ?? null,
			'subtitle' => $_POST['subtitle'] ?? null,
			'content' => $_POST['content'] ?? null,
			'excerpt' => $_POST['excerpt'] ?? null,
			'status' => $_POST['status'] ?? '1',
			'url' => $_POST['url'] ?? '',
			'media_link' => $_POST['media_link'] ?? '',
			'order' => isset($_POST['order']) ? (int)$_POST['order'] : 0
		];
		
		try {
			$newId = $itemModel->create($data);
			$message = "Item creado exitosamente con ID: $newId";
			$messageType = 'success';

			$parentId = $parent;
		} catch (Exception $e) {
			$message = 'Error al guardar el item: ' . $e->getMessage();
			$messageType = 'error';
		}
	}
}else{
	$parentId = isset($_GET['item']) && $_GET['item'] !== '' ? (int)$_GET['item'] : null;
}


$actual_item = null;

if($parentId !== null) {
	$items = $itemModel->getChildren($parentId);
	$actual_item = $itemModel->getById($parentId);
}else{
	$items = $itemModel->getRootItems();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin - TinyEdit</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		body { background:#f8f9fa; }
		.table-actions { min-width:140px; }
		.small-muted { font-size:0.85rem; color:#6c757d; }
	</style>
</head>
<body>

<nav class="navbar bg-dark border-bottom border-body" data-bs-theme="dark">
	<div class="container-fluid">
		<a class="navbar-brand">Administrador</a>
		<div class="d-flex"><button class="btn btn-sm btn-secondary" type="button">Cerrar sesión</button></div>
	</div>
</nav>

<?php if ($message): ?>
	<div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show m-3" role="alert">
		<?php echo $message; ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<div class="container">
	<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
		<h4><?php echo ($parentId !== null) ? 'Items de "' . h($actual_item['shortname'] . '"') : 'Lista de items'; ?></h4>
		<div class="d-flex gap-2">
			<?php
			echo ($parentId!=NULL) ? '<a href="admin.php?item=' . $actual_item['parent_id'] . '" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Volver atrás</a>' : '';
			?>
			<button type="button" class="btn btn-sm btn-success" onclick="setAction()">
				<i class="bi bi-plus-circle"></i> Agregar
			</button>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-body">
			<h2 class="h5"></h2>
			<div class="table-responsive">
				<table class="table table-striped table-hover align-middle">
					<thead>
						<tr>
							<th>ID</th>
							<th>Título</th>
							<th>Shortname</th>
							<th>Estado</th>
							<th class="table-actions">Acciones</th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($items)): ?>
						<tr><td colspan="6" class="text-center small-muted">No hay items.</td></tr>
					<?php else: ?>
						<?php foreach ($items as $r):
								$id = (int)$r['id'];
								$childrenCount = $itemModel->countChildren($id);
						?>
						<tr>
							<td style="width: 50px;"><?php echo h($id); ?></td>
							<td><?php echo h($r['title'] ?? ''); ?></td>
							<td><?php echo h($r['shortname'] ?? ''); ?></td>
							<td><?php echo $r['status'] == 1 ? 'Activo' : 'Inactivo' ; ?></td>
							<td style="width: 200px;">
								<div class="d-flex gap-2">
									<a href="?item=<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Abrir hijos">
										<i class="bi bi-folder2-open"></i> Abrir
									</a>

									<button type="button" class="btn btn-sm btn-primary" onclick="setAction(<?php echo $id; ?>)" title="Editar">
										<i class="bi bi-pencil"></i> Editar
									</button>

								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Modal para agregar Item -->
	<div class="modal fade" id="modalAddItem" tabindex="-1" aria-labelledby="modalAddItemLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="modalAddItemLabel">Agregar nuevo item <?php echo ($parentId!=NULL) ? 'para "' . $actual_item['shortname'] . '"' : 'general'; ?></h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="formAddItem" method="POST" action="admin.php">
					<div class="modal-body">
						<div class="mb-3">
							<label for="itemShortname" class="form-label">Shortname <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="itemShortname" name="shortname" placeholder="Ej: home" required>
						</div>
						<div class="mb-3">
							<label for="itemTitle" class="form-label">Título</label>
							<input type="text" class="form-control" id="itemTitle" name="title" placeholder="Ej: Mi página principal">
						</div>
						<div class="mb-3">
							<label for="itemSubtitle" class="form-label">Subtítulo</label>
							<input type="text" class="form-control" id="itemSubtitle" name="subtitle" placeholder="Subtítulo opcional">
						</div>
						<div class="mb-3">
							<label for="itemContent" class="form-label">Contenido</label>
							<textarea class="form-control" id="itemContent" name="content" rows="5" placeholder="Contenido del item"></textarea>
						</div>
						<div class="mb-3">
							<label for="itemExcerpt" class="form-label">Extracto</label>
							<textarea class="form-control" id="itemExcerpt" name="excerpt" rows="2" placeholder="Resumen corto"></textarea>
						</div>
						<div class="mb-3">
							<label for="itemSlug" class="form-label">URL</label>
							<input type="text" class="form-control" id="itemSlug" name="url" placeholder="Ej: http://ejemplo.com">
						</div>
						<div class="mb-3">
							<label for="itemSlug" class="form-label">Link de imagen o video</label>
							<input type="text" class="form-control" id="itemSlug" name="media_link" placeholder="Ej: http://ejemplo.com/imagen.jpg">
						</div>
						<div class="mb-3">
							<label for="itemStatus" class="form-label">Estado</label>
							<select class="form-select" id="itemStatus" name="status" required>
								<option value="1">Activo</option>
								<option value="0">Inactivo</option>
							</select>
						</div>
						<div class="mb-3">
							<label for="itemOrder" class="form-label">Orden</label>
							<input type="number" class="form-control" id="itemOrder" name="order" value="0" min="0">
						</div>
						<input type="hidden" id="type_action" name="type_action" value="create">
						<input type="hidden" id="item_id" name="id" value="">
						<input type="hidden" name="parent_id" value="<?php echo $parentId !== null ? $parentId : ''; ?>">
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar Item</button>
					</div>
				</form>
			</div>
		</div>
	</div>

</div>

<script>
	function setAction(id = null) {
		const typeActionInput = document.getElementById('type_action');
		const itemIdInput = document.getElementById('item_id');
		const modal = new bootstrap.Modal(document.getElementById('modalAddItem'));
		
		if (id) {
			// Editar
			typeActionInput.value = 'update';
			itemIdInput.value = id;
		} else {
			// Crear
			typeActionInput.value = 'create';
			itemIdInput.value = '';
		}
		
		modal.show();
	}
</script>
<!-- Bootstrap JS (opcional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>