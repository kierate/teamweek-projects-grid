<!DOCTYPE html>
<html>
	<head>
		<title>Scheduling with TeamWeek</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css" rel="stylesheet">
		<link href="style.css" rel="stylesheet">
	</head>

	<body>
		<div class="container">
			<div class="container-fluid header">
				<p class="lead">Project requirements and resource utilisation with </p>
				<div class="logo"></div>
			</div>
			
			<div class="container-fluid">
				<div class="row-fluid">
					<div class="span6">
						<div class="form-fieldset-heading-label content-configure">
							<div class="config-row">
								<label class="description">Week starting</label>
								<div class="input-append change-starting-date">
									<input id="week-start-change" type="text" value="<?= date('d/m/Y', strtotime($starting_day)) ?>">
									<a href="<?= htmlspecialchars($url_week_before) ?>" class="btn" title="Week Before">&lt;</a>
									<a href="<?= htmlspecialchars($url_week_after) ?>" class="btn" title="Week After">&gt;</a>
								</div>
							</div>
							<div class="config-row">
								<label class="description">Weeks to show</label>
								<div class="input-append change-week">
									<input id="week-count-change" type="text" placeholder="<?= $weeks_to_show ?> weeks" data-value="<?= $weeks_to_show ?>">
									<a href="<?= htmlspecialchars($url_week_less) ?>" class="btn" title="Show 1 week less">-</a>
									<a href="<?= htmlspecialchars($url_week_more) ?>" class="btn" title="Show 1 week more">+</a>
								</div>
							</div>
							<div class="config-row">
								<label class="description">Switch to</label>
								<div class="btn-group switch-to-week-month">
										<a href="<?= htmlspecialchars($url_this_week) ?>" class="btn" title="This Week">This Week</a>
										<a href="<?= htmlspecialchars($url_this_month) ?>" class="btn" title="This Month">This Month</a>
								</div>
							</div>
							<div class="config-row">
								<label class="description">Show fully scheduled projects</label>
								
								<label class="radio inline">
									<input type="radio" name="fully-scheduled-projects" value="show" id="scheduled-projects-show" <?= !$hide_fully_scheduled_projects ? 'checked' : '' ?>>
									Yes
								</label>
								<label class="radio inline">
									<input type="radio" name="fully-scheduled-projects" value="hide" id="scheduled-projects-hide" <?= $hide_fully_scheduled_projects ? 'checked' : '' ?>>
									No
								</label>
							</div>
							<div class="config-row">
								<label class="description">Show archived projects</label>
								<label class="radio inline">
									<input type="radio" name="show-archived-projects" value="with-tasks" id="show-archived-projects-with-tasks" checked>
									Only with tasks
								</label>
								<label class="radio inline">
									<input type="radio" name="show-archived-projects" value="none" id="show-archived-projects-none">
									None
								</label>
								<label class="radio inline">
									<input type="radio" name="show-archived-projects" value="all" id="show-archived-projects-all">
									All
								</label>
							</div>
							<div class="config-row">
							</div>
						</div>
					</div>

					<div class="span6">
						<div class="form-fieldset-heading-label content-utilisation">
							<div class="utilisation-wrapper">
								<div class="pull-left graph">
									<div class="util">
										<canvas id="utilisation-progress" width="160" height="160"></canvas>
										<div class="util-inner">
											<div class="overlay" id="total-utilisation-percentage" data-total-utilisation="<?= $total_utilisation ?>" data-total-available="<?= $total_available ?>" data-total-allocated="<?= $total_allocated ?>">
												<h2><?= $total_utilisation ?>%</h2>
											</div>
										</div>
									</div>
								</div>

								<div class="pull-left summary">
									<div id="util-info">
										<div class="part pull-left">
											<div class="util-info-item">
												<span class="util-info-number"><?= $total_available ?></span>
												<span class="util-info-description">Days in period (<?= $users_count ?> members)</span>
											</div>
											<div class="util-info-item " id="util-info-scheduled">
												<span class="util-info-number"><?= $total_allocated ?></span>
												<span class="util-info-description">Days already scheduled</span>
											</div>
											<?php
											$value = $total_available - $total_allocated;
											$overscheduled = $value < 0;
											$value_info = '<span class="util-info-number">' . $value . '</span>
															<span class="util-info-description">Days not yet scheduled</span>';
											if ($overscheduled) {
												$value_info = '<span class="util-info-number">' . $value * -1 . '</span>
																<span class="util-info-description">Days overscheduled</span>';
											}
											?>
											<div class="util-info-item<?= $overscheduled ? ' overscheduled' : ''?>" id="util-info-unscheduled">
												<?= $value_info ?>
											</div>
										</div>

										<div class="part pull-left">
											<?php if (isset($bank_holiday_info) && isset($holiday_info)) { ?>

											<div class="util-info-item" id="bank_holiday_info">
												<span class="util-info-number"><?= $bank_holiday_info['total_allocated'] ?></span>
												<span class="util-info-description"><?= $bank_holiday_info['info_display_name']?></span>
											</div>
											<div class="util-info-item" id="holiday_info">
												<span class="util-info-number"><?= $holiday_info['total_allocated'] ?></span>
												<span class="util-info-description"><?= $holiday_info['info_display_name']?></span>
											</div>

											<?php } ?>

										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="container-fluid">
				<div class="row-fluid">
					<div class="span12 left-column">
						<div class="projects-header">
							<h2>Projects</h2>
							<div>
								<form action="<?= htmlspecialchars($url_force_refresh_action) ?>" method="POST" id="projects-refresh">
									<input type="hidden" name="force_refresh" value="1" />
									<button type="submit" class="btn"><i class="icon-refresh"></i></button>
								</form>
								<a href="<?= htmlspecialchars($url_pdf_download) ?>"
										title="Get schedule for selected period" class="btn"
										target="_blank">Download PDF</a>
							</div>
						</div>
						<?php
						if (count($project_table_data) > 0) {
						?>

						<div class="project-table-wrapper">
							<table class="table project-requirements">
								<thead>
									<tr>
										<th class="project-color">&nbsp;</th>
										<th class="project-name">&nbsp;</th>
										<?php
										foreach (array_keys(current($project_table_data)) as $heading) {
											if ($heading != "totals") {
												?>
												<th class="week"><?= date('d/m', strtotime($heading)) ?></th>
												<?php
											} else {
												?>
												<th class="project-totals">Totals</th>
												<?php
											}
										}
										?>
									</tr>
								</thead>

								<tbody>
									<?php
									$tabindex = 1;
									foreach ($project_table_data as $project_id => $week_data) {									
										$archived_class = '';
										if (!$project_metadata[$project_id]['active']) {
											$archived_class = 'archived';

											$archived_class .= $week_data['totals']['allocated'] == 0 ? ' no-tasks-allocated' : ' has-tasks-allocated';
										}

										$complete_class = '';
										if ($week_data['totals']['allocated'] == $week_data['totals']['required'] &&
											$week_data['totals']['utilisation'] == 100) {
											$complete_class = ' complete';
											if ($hide_fully_scheduled_projects) {
												$complete_class .= ' hide';
											}
										}
									?>
									<tr class="<?= $archived_class . $complete_class ?>" data-project-id="<?= $project_id ?>">
										<th class="project-color" style="background-color: <?= $project_metadata[$project_id]['color'] ?>"></th>
										<th class="project-name" data-project-name="<?= htmlspecialchars($project_metadata[$project_id]['name']) ?>">
											<a href="<?= $url_teamweek_account_base . 'projects/' . $project_id . '/edit' ?>" target="_blank" tabindex="-1"><?= $project_metadata[$project_id]['name'] ?></a>
										<?php
										if (!empty($project_metadata[$project_id]['client_name'])) {
											?>
											<span class="client-name"><?= $project_metadata[$project_id]['client_name'] ?></span>
											<?php
										}
										?>
										</th>
										<?php
										foreach ($week_data as $heading => $cell) {
											if ($heading != "totals") {
													$allocated_class = $cell['allocated'] > 0 ? 'has-tasks-allocated' : 'no-tasks-allocated';
												?>
												<td class="week <?= $allocated_class ?>" data-week="<?= $heading ?>">
													<span class="project-allocated" data-original-value="<?= $cell['allocated'] ?>"><?= $cell['allocated'] ?></span> /
													<div class="project-required view-mode" tabindex="<?= $tabindex++ ?>" data-project-id="<?= $project_id ?>" data-week="<?= $heading ?>">
														<span class="requirement-text"><?= $cell['required'] ?></span>
														<input type="text" value="<?= $cell['required'] ?>" name="requirement" class="requirement-input"/>
														<div class="buttons">
															<div class="project-required-button project-required-edit">
																<a><i class="icon-edit"></i></a>
															</div>
															<div class="project-required-button project-required-save"><a><i class="icon-ok"></i></a></div>
															<div class="project-required-button project-required-cancel"><a><i class="icon-remove"></i></a></div>
															<div class="project-required-button project-required-saving"><a><i class="icon-refresh spinning"></i></a></div>
														</div>
													</div>
												</td>
												<?php
											} else {
												$color = 'yellow';//by default the progress bar is yellow i.e. it's not finished
												if ($cell['utilisation'] == 100) {
													$color = 'green'; //finished and correct
												} elseif ($cell['utilisation'] > 100) {
													$color = 'red'; //too many days allocated vs what's required
												}
												?>
												<td class="project-totals">
													<span class="values"><?= $cell['allocated'] ?> / <?= $cell['required'] ?></span>
													<div class="progressbar progressbar-<?= $color ?>">
														<?php
														$total_width = $total_title = $cell['utilisation'];
														if ($total_title === "N/A") {
															$total_width = 0;
														} elseif ($cell['allocated'] > $cell['required']) {
															$total_width = 100;
														}
														?>
														<div class="progressbar-inner" style="width: <?= $total_width ?>%" title="<?= $total_title ?>%"></div>
													</div>
												</td>
												<?php
											}
										?>

										<?php
										}
										?>
									</tr>
									<?php
									}
									?>
								</tbody>
							</table>
						</div>

						<?php
						} else {
						?>
						<p>No projects found.</p>
						<?php
						}
						?>
					</div>
				</div>

				<div class="row-fluid">
					<div class="span12 left-column">

						<div class="projectless-header">
							<h3>Tasks without a project</h3>
							<div><span>These tasks are not counted towards the utilisation total</span></div>
						</div>
						
						<?php
						if (count($projectless_tasks) > 0) {
						?>
							<ul id="tasks-no-projects" class="unstyled">
							<?php
							foreach ($projectless_tasks as $task) {
								$display = $task['comment'];
								if (isset($task['start_date']) && isset($task['end_date'])) {
									$display .= " (from " . date('d/m', strtotime($task['start_date'])) . " to " . date('d/m', strtotime($task['end_date'])) . ")";
								}
								?>
								<li><span class="bullet"></span><?= $display ?></li>
								<?php
								}
							?>
							</ul>
						<?php
						} else {
						?>
						<p>There are currently no tasks that do not belong to a project.</p>
						<?php
						}
						?>
					</div>
				</div>
			</div>
		</div>

		<script src="http://code.jquery.com/jquery.js"></script>
		<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/js/bootstrap.min.js"></script>
		<script>
		var config = {
			url_week_start_change:     <?= json_encode($url_week_start_change) ?>,
			url_week_count_change:     <?= json_encode($url_week_count_change) ?>,
			url_ajax_post_requirement: <?= json_encode($url_ajax_post_requirement) ?>,
			starting_day:              <?= json_encode($starting_day) ?>,
			weeks_to_show:             <?= json_encode($weeks_to_show) ?>,
			total_available:           <?= json_encode($total_available) ?>,
			total_allocated:           <?= json_encode($total_allocated) ?>,
			total_utilisation:         <?= json_encode($total_utilisation) ?>,
			bank_holiday_project_name: <?= json_encode($bank_holiday_info['project_name']) ?>,
			holiday_project_name:      <?= json_encode($holiday_info['project_name']) ?>
		};
		</script>
		<script src="script.js"></script>
	</body>
</html>