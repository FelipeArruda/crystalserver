<?php
/**
 * Shop transactions queue editor.
 */

defined('MYAAC') or die('Direct access not allowed!');

$title = 'Shop Transactions';
$use_datatable = true;

csrfProtect();

$queueTable = 'z_ots_comunication';
$historyTable = 'z_shop_history';
$allowedTypes = ['item', 'addon', 'mount'];

if (!$db->hasTable($queueTable)) {
	echo '<div class="alert alert-warning">Table <strong>' . $queueTable . '</strong> was not found in the database.</div>';
	return;
}

if (isset($_POST['save_transaction'])) {
	$id = (int) ($_POST['id'] ?? 0);
	$name = trim((string) ($_POST['name'] ?? ''));
	$param1 = (int) ($_POST['param1'] ?? 0);
	$param2 = (int) ($_POST['param2'] ?? 0);
	$param3 = (int) ($_POST['param3'] ?? 0);
	$param4 = (int) ($_POST['param4'] ?? 0);
	$param5 = trim((string) ($_POST['param5'] ?? ''));
	$param6 = trim((string) ($_POST['param6'] ?? ''));

	if ($id <= 0) {
		error('Invalid transaction id.');
	}
	else if ($name === '') {
		error('Player name cannot be empty.');
	}
	else if (!in_array($param5, $allowedTypes, true)) {
		error('Delivery type must be item, addon or mount.');
	}
	else {
		$stmt = $db->prepare(
			"UPDATE `{$queueTable}`
			 SET `name` = :name,
			     `param1` = :param1,
			     `param2` = :param2,
			     `param3` = :param3,
			     `param4` = :param4,
			     `param5` = :param5,
			     `param6` = :param6
			 WHERE `id` = :id"
		);

		$stmt->execute([
			':id' => $id,
			':name' => $name,
			':param1' => $param1,
			':param2' => $param2,
			':param3' => $param3,
			':param4' => $param4,
			':param5' => $param5,
			':param6' => $param6,
		]);

		success('Transaction updated. The order will be retried automatically when the player logs in or on the next shop queue cycle.');
	}
}

if (isset($_POST['delete_transaction'])) {
	$id = (int) ($_POST['id'] ?? 0);
	if ($id <= 0) {
		error('Invalid transaction id.');
	}
	else {
		$stmt = $db->prepare("DELETE FROM `{$queueTable}` WHERE `id` = :id");
		$stmt->execute([':id' => $id]);

		if ($db->hasTable($historyTable)) {
			$historyStmt = $db->prepare(
				"UPDATE `{$historyTable}`
				 SET `trans_state` = 'deleted'
				 WHERE `comunication_id` = :id AND `trans_state` <> 'realized'"
			);
			$historyStmt->execute([':id' => $id]);
		}

		success('Transaction removed from the pending queue.');
	}
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editRow = null;

if ($editId > 0) {
	$stmt = $db->prepare("SELECT * FROM `{$queueTable}` WHERE `id` = :id LIMIT 1");
	$stmt->execute([':id' => $editId]);
	$editRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

	if ($editRow === null) {
		warning('Selected transaction was not found. It may have already been delivered or removed.');
	}
}

$query = "
	SELECT q.`id`, q.`name`, q.`param1`, q.`param2`, q.`param3`, q.`param4`, q.`param5`, q.`param6`";

if ($db->hasTable($historyTable)) {
	$query .= ",
		h.`trans_state`,
		h.`trans_start` AS `history_start`,
		h.`trans_real`,
		h.`to_account`";
}
else {
	$query .= ",
		NULL AS `trans_state`,
		NULL AS `history_start`,
		NULL AS `trans_real`,
		NULL AS `to_account`";
}

$query .= "
	FROM `{$queueTable}` q";

if ($db->hasTable($historyTable)) {
	$query .= "
		LEFT JOIN `{$historyTable}` h
			ON h.`comunication_id` = q.`id`";
}

$query .= "
	ORDER BY q.`id` DESC";

$rows = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row">
	<div class="col-lg-5">
		<div class="card card-info card-outline">
			<div class="card-header">
				<h5 class="m-0"><?php echo $editRow ? 'Edit Pending Transaction #' . (int) $editRow['id'] : 'Instructions'; ?></h5>
			</div>
			<div class="card-body">
				<?php if ($editRow): ?>
					<form method="post" action="<?php echo ADMIN_URL; ?>?p=shop_transactions&id=<?php echo (int) $editRow['id']; ?>">
						<?php csrf(); ?>
						<input type="hidden" name="id" value="<?php echo (int) $editRow['id']; ?>">

						<div class="form-group">
							<label>Player</label>
							<input class="form-control" type="text" name="name" maxlength="255" value="<?php echo escapeHtml($editRow['name']); ?>" required>
						</div>

						<div class="form-group">
							<label>Delivery type</label>
							<select class="form-control" name="param5">
								<?php foreach ($allowedTypes as $type): ?>
									<option value="<?php echo $type; ?>"<?php echo($editRow['param5'] === $type ? ' selected' : ''); ?>><?php echo ucfirst($type); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="form-row row">
							<div class="form-group col-md-6">
								<label>Param1</label>
								<input class="form-control" type="number" name="param1" value="<?php echo (int) $editRow['param1']; ?>" required>
							</div>
							<div class="form-group col-md-6">
								<label>Param2</label>
								<input class="form-control" type="number" name="param2" value="<?php echo (int) $editRow['param2']; ?>" required>
							</div>
						</div>

						<div class="form-row row">
							<div class="form-group col-md-6">
								<label>Param3</label>
								<input class="form-control" type="number" name="param3" value="<?php echo (int) $editRow['param3']; ?>">
							</div>
							<div class="form-group col-md-6">
								<label>Param4</label>
								<input class="form-control" type="number" name="param4" value="<?php echo (int) $editRow['param4']; ?>">
							</div>
						</div>

						<div class="form-group">
							<label>Offer name</label>
							<input class="form-control" type="text" name="param6" maxlength="255" value="<?php echo escapeHtml($editRow['param6']); ?>">
						</div>

						<div class="d-flex justify-content-between">
							<button class="btn btn-info" type="submit" name="save_transaction" value="1">Save changes</button>
							<a class="btn btn-secondary" href="<?php echo ADMIN_URL; ?>?p=shop_transactions">Cancel</a>
						</div>
					</form>
				<?php else: ?>
					<p>This page edits rows from <code>z_ots_comunication</code>, which is the pending delivery queue processed by <code>gesior_shop_system.lua</code>.</p>
					<ul class="mb-0">
						<li>Fix wrong item IDs, counts, delivery type, character name, or offer label.</li>
						<li>After saving, the order stays pending and will be retried automatically.</li>
						<li>Delete only removes the pending queue entry. Use it for broken or duplicate orders.</li>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-lg-7">
		<div class="card card-info card-outline">
			<div class="card-header">
				<h5 class="m-0">Pending Queue</h5>
			</div>
			<div class="card-body">
				<?php if (empty($rows)): ?>
					<div class="alert alert-success mb-0">There are no pending shop transactions.</div>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-striped table-bordered table-sm">
							<thead>
							<tr>
								<th>ID</th>
								<th>Player</th>
								<th>Type</th>
								<th>Params</th>
								<th>Offer</th>
								<th>History</th>
								<th>Actions</th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($rows as $row): ?>
								<tr>
									<td><?php echo (int) $row['id']; ?></td>
									<td><?php echo escapeHtml($row['name']); ?></td>
									<td><?php echo escapeHtml($row['param5']); ?></td>
									<td>
										<small>
											p1=<?php echo (int) $row['param1']; ?><br>
											p2=<?php echo (int) $row['param2']; ?><br>
											p3=<?php echo (int) $row['param3']; ?><br>
											p4=<?php echo (int) $row['param4']; ?>
										</small>
									</td>
									<td><?php echo escapeHtml((string) $row['param6']); ?></td>
									<td>
										<small>
											state=<?php echo escapeHtml((string) ($row['trans_state'] ?? 'pending')); ?><br>
											account=<?php echo escapeHtml((string) ($row['to_account'] ?? '-')); ?>
										</small>
									</td>
									<td class="text-nowrap">
										<a class="btn btn-info btn-sm" href="<?php echo ADMIN_URL; ?>?p=shop_transactions&id=<?php echo (int) $row['id']; ?>">Edit</a>
										<form method="post" action="<?php echo ADMIN_URL; ?>?p=shop_transactions" style="display:inline-block;" onsubmit="return confirm('Delete pending transaction #<?php echo (int) $row['id']; ?>?');">
											<?php csrf(); ?>
											<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
											<button class="btn btn-danger btn-sm" type="submit" name="delete_transaction" value="1">Delete</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
