<?php

require_once 'model.php';

$itemModel = new Item();

$parentId = isset($_GET['item']) && $_GET['item'] !== '' ? (int)$_GET['item'] : null;

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

<div class="container">
	<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
		<h4><?php echo ($parentId !== null) ? 'Items de "' . h($actual_item['shortname'] . '"') : 'Lista de items'; ?></h4>
		<div style="height: 40px;">
			<?php
			echo ($parentId!=NULL) ? '<a href="admin.php?item=' . $actual_item['parent_id'] . '" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Volver atrás</a>' : '';
			?>
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
						<tr><td colspan="6" class="text-center small-muted">No hay items raíz.</td></tr>
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
									<?php if ($childrenCount > 0): ?>
										<a href="?item=<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Abrir hijos">
											<i class="bi bi-folder2-open"></i> Abrir
										</a>
									<?php else: ?>
										<button class="btn btn-sm btn-outline-secondary" disabled title="No tiene hijos">
											<i class="bi bi-folder2"></i> Abrir
										</button>
									<?php endif; ?>

									<a href="?item=<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Abrir hijos">
										<i class="bi bi-pencil"></i> Editar
									</a>

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

</div>

<!-- Bootstrap JS (opcional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>