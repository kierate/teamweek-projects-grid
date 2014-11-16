// ------------------------------- header -----------------------------
$('#week-start-change').keyup(function(event) { //handle the date field submission
	switch (event.keyCode) {
		case 13:
			//pressed enter -> redirect
			var parts = $(event.target).prop('value').split('/'); //assuming UK date format, something to review
			var new_date = parts[2] + '-' + parts[1] + '-' + parts[0];
			window.location.href = getUrl(config.url_week_start_change + new_date);
			break;
		default:
			break;
	}
}).focus(function() { //when clicked show the value instead of the placeholder
	$(this).prop('value', $(this).attr('data-value'));
	$(this).select();
}).focusout(function() { //when focus is lost the placeholder will be shown
	$(this).prop('value', '');
});
//handle the week change submission
$('#week-count-change').keyup(function(event) {
	switch (event.keyCode) {
		case 13:
			//pressed enter -> redirect
			window.location.href = getUrl(config.url_week_count_change + $(event.target).prop('value'));
			break;
		default:
			break;
	}
});
$("#scheduled-projects-show, #scheduled-projects-hide").click(function() {
	//show or hide the completed projects
	//fadein and fadeout instead of toggle as some projects might get completed while between ticks
	if ($("#scheduled-projects-hide").is(':checked')) {
		$("table.project-requirements tr.complete").fadeOut();
	} else {
		$("table.project-requirements tr.complete").fadeIn();
	}
	$(".change-weeks a").each(function() {
		$(this).attr('href', getUrl($(this).attr('href')));
	});
	$("#projects-refresh").attr('action', getUrl($("#projects-refresh").attr('action')));
});

$("#show-archived-projects-with-tasks").click(function() {
	$("table.project-requirements tr.archived.no-tasks-allocated").animate({ height: 0, opacity: 0 }, 'slow', function() {
		//reset the css to just display:none - this means fadeIn() can be used on the element later
		$("table.project-requirements tr.archived.no-tasks-allocated").attr('style', 'display: none');
	});		
});
$("#show-archived-projects-none").click(function() {
	$("table.project-requirements tr.archived").animate({ height: 0, opacity: 0 }, 'slow', function() {
		//reset the css to just display:none - this means fadeIn() can be used on the element later
		$("table.project-requirements tr.archived").attr('style', 'display: none');
	});
});
$("#show-archived-projects-all").click(function() {
	//fade in all archived projects
	$("table.project-requirements tr.archived").fadeIn('slow');
});

//update the totals and the progress circle based on the change in allocation
//(i.e. the diff is either positive or negative)
var updateTotalsBasedOnRowAllocationChange = function(diff) {
	diff = parseFloat(diff);
	//no change
	if (diff == 0) {
		return;
	}

	//update the allocation total
	var total_allocated = parseFloat($('#total-utilisation-percentage').attr('data-total-allocated'));
	total_allocated += diff;
	$('#total-utilisation-percentage').attr('data-total-allocated', total_allocated);

	//get the current available total
	var total_available = parseFloat($('#total-utilisation-percentage').attr('data-total-available'));

	//calculate the new utilisation and store the old one
	var new_utilisation = parseFloat((total_allocated / total_available * 100).toFixed(1));
	var old_utilisation = $('#total-utilisation-percentage').attr('data-total-utilisation');

	//update the display of the new utilisation
	$('#total-utilisation-percentage').attr('data-total-utilisation', new_utilisation).find('h2').fadeOut(200, function() {
		$(this).html(new_utilisation + '%');
	}).fadeIn(200);

	//update the display of the allocted today
	$('#util-info-scheduled').fadeOut(200, function() {
		$(this).find('span').html(total_allocated);
	}).fadeIn(200);

	//update the display of the un/over scheduled
	$('#util-info-unscheduled').fadeOut(200, function() {
		var unscheduled = total_available - total_allocated;
		if (unscheduled < 0) {
			$(this).toggleClass('overscheduled');
			$(this).find('span.util-info-number').html(-1 * unscheduled);
			$(this).find('span.util-info-description').html('Days overscheduled');
		} else {
			$(this).removeClass('overscheduled');
			$(this).find('span.util-info-number').html(unscheduled);
			$(this).find('span.util-info-description').html('Days not yet scheduled');
		}
	}).fadeIn(200);

	//animate the progress of the total utilisation from old to new (either increase or decrease)
	animateUtilisationProgress(old_utilisation, new_utilisation);
}
// --------------------------------------------------------------------


// ------------------- edit, save, cancel buttons ---------------------
$(".project-required-save").click(function(event) {
	//make sure that we do not propagate this event further
	event.stopPropagation();

	//hide the save and cancel buttons
	var project_div = $(this).parents("div.project-required");
	project_div.find("div.buttons div.project-required-cancel").hide();
	$(this).hide();
	//show the saving spinner
	project_div.find("div.buttons div.project-required-saving").attr("style", "display: inline");

	//update statuses
	project_div.addClass("save-in-progress");
	project_div.removeClass("update-failure");

	$.ajax({
		type: "POST",
		url: config.url_ajax_post_requirement,
		data: {
			action: "edit_project_week_requirement",
			week: project_div.attr("data-week"),
			project_id: project_div.attr("data-project-id"),
			requirement: project_div.children("input.requirement-input").prop("value"),
			force_refresh: 1,
			week_start: config.starting_day,
			week_count: config.weeks_to_show
		},
		dataType: "json",
		context: this //set the context of the callbacks
	})
	.done(function(data) {
		//push the input value to the span and update the statuses
		var project_div = $(this).parents("div.project-required");
		project_div.removeClass("save-in-progress");
		project_div.toggleClass("update-success");
		project_div.children("span.requirement-text").html(project_div.children("input.requirement-input").val());
		
		window.setTimeout(function() {
			//change from edit to view mode
			project_div.toggleClass("update-success");
			project_div.find("div.buttons div.project-required-saving").hide();
			project_div.removeClass("edit-mode").addClass("view-mode");

			//if there were errors then the border needs resetting
			project_div.removeAttr('style');

			//remove the style attribute as the css controls what gets shown in view-mode and edit-mode
			project_div.find("div.buttons div.project-required-save").removeAttr('style');
			project_div.find("div.buttons div.project-required-cancel").removeAttr('style');
			project_div.find("div.buttons div.project-required-saving").removeAttr('style');

			//get all the data
			$.each(data, function(project_id, week_data) {
				//at the moment just process the current project
				if (project_id == project_div.attr("data-project-id")) {

					var old_allocation = 0;
					var new_allocation = 0;

					$.each(week_data, function(column, content) {
						//process the weekly data (not the totals column)
						if (column != 'totals') {
							var project_week_cell = $("tr[data-project-id=" + project_id + "] td.week[data-week=" + column + "]");
							var span_allocated = project_week_cell.find("span.project-allocated");
							
							//save the allocation values - both new and old
							old_allocation += parseFloat(span_allocated.attr('data-original-value'));
							new_allocation += content.allocated;

							//change allocated and required values - fadeout, change, fadein, reset styles
							span_allocated.fadeOut(200, function() {
								$(this).attr('data-original-value', content.allocated).html(content.allocated);
							}).fadeIn(200, function() {
								$(this).removeAttr('style');
							});

							//update the requirement span
							if (project_week_cell.find("div.view-mode span.requirement-text").length == 1) {
								//if we are in view mode then fade in and out
								project_week_cell.find("div.project-required span.requirement-text").fadeOut(200, function() {
									$(this).html(content.required);
								}).fadeIn(200, function() {
									$(this).removeAttr('style');
								});
							} else {
								//otherwise (edit mode) just modify the span directly and it will be displayed later
								project_week_cell.find("div.project-required span.requirement-text").html(content.required);
							}
						}
					});

					//update the totals in the summary section
					var allocation_diff = new_allocation - old_allocation;
					updateTotalsBasedOnRowAllocationChange(allocation_diff);

					//update the totals column
					var project_totals_cell = $("tr[data-project-id=" + project_id + "] td.project-totals");
					project_totals_cell.find("span.values").fadeOut(200, function() {
							$(this).html(week_data.totals.allocated + ' / ' + week_data.totals.required);
						}).fadeIn(200);

					//update the bank/public holidays and the holidays/annual leave
					if ($("tr[data-project-id=" + project_id + "] th.project-name a").html() == config.bank_holiday_project_name) {
						$('#bank_holiday_info').fadeOut(200, function() {
							$('#bank_holiday_info span.util-info-number').html(week_data.totals.allocated);
						}).fadeIn(200);
						
					} else if ($("tr[data-project-id=" + project_id + "] th.project-name a").html() == config.holiday_project_name) {
						$('#holiday_info').fadeOut(200, function() {
							$('#holiday_info span.util-info-number').html(week_data.totals.allocated);
						}).fadeIn(200);
					}


					//deal with colours and percentage for the progress bar
					var progress_bar_color = 'yellow'; //by default the progress bar is yellow i.e. it's not finished
					if (week_data.totals.utilisation == 100) {
						progress_bar_color = 'green'; //finished and correct
					} else if (week_data.totals.utilisation > 100) {
						progress_bar_color = 'red'; //too many days allocated vs what's required
					}
					var progress_bar_title = week_data.totals.utilisation,
						progress_bar_width = week_data.totals.utilisation;

					if (week_data.totals.utilisation == "N/A") {
						progress_bar_width = 0;
					} else if (week_data.totals.allocated > week_data.totals.required) {
						progress_bar_width = 100;
					}
					
					if (week_data.totals.utilisation == 100) {
						$("tr[data-project-id=" + project_id + "]").addClass('complete');
					} else {
						$("tr[data-project-id=" + project_id + "]").removeClass('complete');
					}

					project_totals_cell.find("div.progressbar").removeClass().addClass("progressbar progressbar-" + progress_bar_color);
					project_totals_cell.find("div.progressbar-inner").attr('title', progress_bar_title + '%');
					project_totals_cell.find("div.progressbar-inner").css('width', progress_bar_width + '%');
				}
			});
		}, 500);

	})
	.fail(function() {
		//update the statuses
		var project_div = $(this).parents("div.project-required");
		project_div.removeClass("save-in-progress");
		project_div.addClass("update-failure");

		window.setTimeout(function() {
			//remove the spinner and show the save/cancel buttons
			project_div.find("div.buttons div.project-required-saving").hide();
			project_div.find("div.buttons div.project-required-saving").removeAttr('style');
			project_div.find("div.buttons div.project-required-save").removeAttr('style'); //will be shown without a style applied
			project_div.find("div.buttons div.project-required-cancel").removeAttr('style'); //will be shown without a style applied
		}, 1000);
	});

	//make sure that we do not propagate this event further (otherwise $(".view-mode").click() will execute as well)
	event.stopPropagation();
});
$(".project-required-cancel").click(function(event) {
	var project_div = $(this).parents("div.project-required");

	//if there were errors then the border needs resetting
	project_div.removeAttr('style');

	//when cancelling we change from edit to view mode
	project_div.toggleClass('edit-mode view-mode');

	//make sure that we do not propagate this event further (otherwise $(".view-mode").click() will execute as well)
	event.stopPropagation();
});
$(".project-required-edit").click(function(event) {
	//cancel any other editing
	$('div.project-required.edit-mode').find("div.buttons div.project-required-cancel").click();

	//make sure that we do not propagate this event further (otherwise $(".view-mode").click() will execute as well)
	event.stopPropagation();

	var project_div = $(this).parents("div.project-required");
	var input = project_div.children("input.requirement-input");
	var requirement_span = project_div.children("span.requirement-text");

	//the input has to be the same width as the span
	input.width(requirement_span.width()+2);

	//change into edit mode
	project_div.removeClass("view-mode").addClass("edit-mode");

	//and focus on the text input field with the cursor at the end of it
	input.focus();
	var val = input.prop("value");
	input.prop("value", "");
	input.prop("value", val);
	input.focus();
	input.select();
});
//allow the user clicking anywhere on the requirement div to trigger the change to edit mode
$(".view-mode").click(function(event) {
	$(this).find("div.buttons div.project-required-edit").click();
});
$('.requirement-input').keyup(function(event) {
	var project_div = $(event.target).parents("div.project-required");

	switch (event.keyCode) {
		case 13:
			//pressed enter = clicking the save
			project_div.find("div.buttons div.project-required-save").click();
			break;
		case 27:
			//pressed escape = clicking the cancel
			project_div.find("div.buttons div.project-required-cancel").click();
			break;
		default:
			break;
	}
});
// --------------------------------------------------------------------


// ------------- tabbing across the requirement inputs ----------------
$(document).ready(function() {
	//we want to allow tabbing on the requirement inputs only so set the indexes to -1 for other elements
	$("a, button, input").attr('tabindex', '-1');
});
$(".view-mode").focus(function(event) {
	//when focus is gained we want to enable editing
	$(this).find("div.buttons div.project-required-edit").click();
});
$(document).click(function(event) {
	if ($(event.target).parents("div.project-required").length == 0) {
		//cover cases where user clicks away from the input = clicking cancel
		//essentially this checks for any cells in edit mode (and there should only be 1) and imitates clicking the cancel icon
		$('div.project-required.edit-mode').find("div.buttons div.project-required-cancel").click();
	}
});
$('.requirement-input').keydown(function(event) {
	if (event.keyCode == 9) {
		var project_div = $(event.target).parents("div.project-required");
		//pressed tab = clicking the cancel
		project_div.find("div.buttons div.project-required-cancel").click();
		
	}
});
// --------------------------------------------------------------------


// ----------- get updated url with/out done projects -----------------
function getUrl(url)
{
	if ($("#show-hide-completed-toggle").is(':checked')) {
		var qstring = /\?.+$/;
		return url + (qstring.test(url) ? '&' : '?') + 'hide_fully_scheduled_projects';
	} else {
		return url.replace(/(\?|\&)hide_fully_scheduled_projects/, '');
	}
}
// --------------------------------------------------------------------



// ----------------------- utilisation drawing ------------------------
$(document).ready(function() {
	//start at zero
	drawUtilisationProgressAtPercent(0);

	//then animate to the total
	animateUtilisationProgress(0, config.total_utilisation);
});

var bg = document.getElementById('utilisation-progress');
var ctx = bg.getContext('2d');
var imd = null;
var circ = Math.PI * 2;
var quart = Math.PI / 2;

ctx.shadowOffsetX = 0;
ctx.shadowOffsetY = 0;
ctx.shadowBlur = 3;
ctx.lineWidth = 15;

imd = ctx.getImageData(0, 0, 100, 100);

//draw the progress on the circle
var drawUtilisationProgressAtPercent = function(current) {
	ctx.clearRect(0, 0, bg.width, bg.height);
	current /= 100;

	ctx.putImageData(imd, 0, 0);

	var start_bg, end_bg, start_fg, end_fg;
	start_bg = end_fg = circ * current - quart;
	end_bg = start_fg = -quart;
	if (current == 0) {
		start_bg = -quart;
		end_bg = circ * 100 - quart;
	}

	//the grey background (the whole circle)
	ctx.strokeStyle = '#EFEFEF';
	ctx.shadowColor = "rgba(239,239,239, 0.6)";
	ctx.beginPath();
	ctx.arc(80,80, 70, start_bg, end_bg);
	ctx.stroke();
	ctx.closePath();

	//the green foreground (an arc on top of the grey circle background)
	ctx.strokeStyle = '#61C22F';
	ctx.shadowColor = 'rgba(98, 194, 47, 0.6)';
	ctx.beginPath();
	ctx.arc(80,80, 70, start_fg, end_fg);
	ctx.stroke();
	ctx.closePath();
}

//animate the utilisation progress (either up or down)
var animateUtilisationProgress = function(old_total, new_total) {

	//start with a half second delay
	setTimeout(function() {

		var current = Math.ceil(old_total);

		//draw incrementing
		if (old_total < new_total) {
			var drawing_interval = setInterval(function() {
				if (current < new_total) {
					current++;
					drawUtilisationProgressAtPercent(current);
				} else {
					clearInterval(drawing_interval);
				}
			}, 20);

		//draw decrementing
		} else if (old_total > new_total) {
			var drawing_interval = setInterval(function() {
				if (current > new_total) {
					current--;
					drawUtilisationProgressAtPercent(current);
				} else {
					clearInterval(drawing_interval);
				}
			}, 20);
		}

	}, 500);

}
//---------------------------------------------------------------------


