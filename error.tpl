<!DOCTYPE html>
<html>
	<head>
		<title>Scheduling with TeamWeek - Error</title>
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

			<div id="error">
			  <h2>Internal error</h2>
			  <p><?= $error_message ?></p>
			  <p class="stacktrace">
			  	Stacktrace:<br /><?= $error_details ?>
			  </p>
			</div>
		</div>
	</body>
</html>