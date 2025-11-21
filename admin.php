<?php
require_once 'model.php';

$itemModel = new Item();

$parentId = isset($_GET['item']) && $_GET['item'] !== '' ? (int)$_GET['item'] : null;

// Obtener items raíz (parent_id IS NULL) usando model
$rootItems = $itemModel->getRootItems();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin - Items</title>
	<!-- Bootstrap CSS CDN -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Bootstrap Icons -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		/* CSS embebido */
		body { padding: 20px; background:#f8f9fa; }
		.table-actions { min-width:140px; }
		.small-muted { font-size:0.85rem; color:#6c757d; }
	</style>
</head>
<body>
<div class="container">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3">Administrador de Items</h1>
		<div>
			<a href="admin.php" class="btn btn-sm btn-secondary">Volver</a>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-body">
			<h2 class="h5">Items raíz</h2>
			<div class="table-responsive">
				<table class="table table-striped table-hover align-middle">
					<thead>
						<tr>
							<th>ID</th>
							<th>Título</th>
							<th>Shortname</th>
							<th>Tipo</th>
							<th>Estado</th>
							<th class="table-actions">Acciones</th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($rootItems)): ?>
						<tr><td colspan="6" class="text-center small-muted">No hay items raíz.</td></tr>
					<?php else: ?>
						<?php foreach ($rootItems as $r):
								$id = (int)$r['id'];
					// contar hijos mediante model
					$childrenCount = $itemModel->countChildren($id);
						?>
						<tr>
							<td><?php echo h($id); ?></td>
							<td><?php echo h($r['title'] ?? ''); ?></td>
							<td><?php echo h($r['shortname'] ?? ''); ?></td>
							<td><?php echo h($r['type'] ?? ''); ?></td>
							<td><?php echo h($r['status'] ?? ''); ?></td>
							<td>
								<div class="d-flex gap-2">
									<?php if ($childrenCount > 0): ?>
										<a href="?item=<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Abrir hijos">
											<i class="bi bi-folder2-open"></i> Abrir
										</a>
									<?php else: ?>
										<button class="btn btn-sm btn-outline-secondary" disabled title="No tiene hijos">
											<i class="bi bi-folder2"></i> Sin hijos
										</button>
									<?php endif; ?>
									<a href="#" class="btn btn-sm btn-outline-info">Ver</a>
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

	<?php if ($parentId !== null):
		// obtener hijos mediante model
		$children = $itemModel->getChildren($parentId);
	?>

	<div class="card mb-4">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<h2 class="h5">Hijos del item <?php echo h($parentId); ?></h2>
				<a href="admin.php" class="btn btn-sm btn-outline-secondary">Cerrar</a>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover align-middle">
					<thead>
						<tr>
							<th>ID</th>
							<th>Título</th>
							<th>Shortname</th>
							<th>Tipo</th>
							<th>Estado</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($children)): ?>
							<tr><td colspan="5" class="text-center small-muted">No se encontraron hijos.</td></tr>
						<?php else: ?>
							<?php foreach ($children as $ch): ?>
								<tr>
									<td><?php echo h($ch['id']); ?></td>
									<td><?php echo h($ch['title'] ?? ''); ?></td>
									<td><?php echo h($ch['shortname'] ?? ''); ?></td>
									<td><?php echo h($ch['type'] ?? ''); ?></td>
									<td><?php echo h($ch['status'] ?? ''); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif; ?>

</div>

<!-- Bootstrap JS (opcional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>