<!doctype html>
<html lang="ru-RU">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Default Page Title</title>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
	<style type="text/css">
		body {
			font-family: verdana, sans-serif;
			max-width: 1200px;
			margin: 0 auto;
			display: flex;
			align-items: center;
			justify-content: center;
			height: 100vh;
		}
		.form-group {
			padding: 5px 0;
			display: flex;
			width: 100%;
			justify-content: space-between;
			align-items: center;
		}

		.form-control {
			border: 1px solid #ccc;
			padding: .5rem .25rem;
		}
		label {
			margin-right: 1rem;
			font-size: 15px;
		}

		button {
			border: 1px solid #ccc;
			background-color: #fff;
			padding: .5rem 1rem;
			margin-top: 1.2rem;
			cursor: pointer;
		}

		button:hover {
			background-color: #ddd;
		}

		.result {
			font-size: 0.8rem;
		}

		.result p {
			margin-bottom: 0.5rem;
		}

		.result .summary {
			font-size: 1rem;
			font-weight: bold;
		}

		.result .success {
			color: green;
		}

		.result .failure {
			color: red;
		}
	</style>
</head>
<body>
	<form id="newscallback" action="/ajax/mail.php" data-action="ajax" method="post">
		<h3>Форма отправки сообщения</h3>

		<div class="form-group">
			<label>Ваше имя</label>
			<input type="text" class="form-control" name="name">
		</div>

		<div class="form-group">
			<label>Электронная почта</label>
			<input type="text" class="form-control" name="email">
		</div>

		<div class="form-group">
			<label>Номер телефона</label>
			<input type="text" class="form-control" name="phone" data-format="+9 (999) 9999999">
		</div>

		<div class="form-group">
			<label>Дополнительный текст</label>
			<textarea class="form-control" name="text"></textarea>
		</div>

		<div class="form-group" style="display: none;">
			<label>Honeypot</label>
			<input type="text" class="form-control" name="surname"></input>
		</div>

		<input type="hidden" class="form-control" name="addit">
		<input type="hidden" class="form-control" name="form-name">
		<input type="hidden" class="form-control" name="is_ajax" value="Y">

		<button type="submit" class="btn btn-default">Отправить</button>

		<div class="result"></div>
	</form>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			var $form = $('[data-action="ajax"]');
			$('[name="form-name"]', $form).val( $form.attr('id') );

			$form.on('submit', function(event) {
				event.preventDefault();

				$.post(
					$form.attr('action'),
					$form.serialize(),
					function(data, textStatus, xhr) {
						if( 'success' == data.status ) {
							$('.result', $form).fadeOut(400, function() {
								$(this).html( '<div class="success">' + data.message + '</div>' ).fadeIn();
							});
						}
						else {
							$('.result', $form).fadeOut(400, function() {
								$(this).html( '<div class="failure">' + data.message + '</div>' ).fadeIn();
							});
						}
					},
					'JSON').fail(function() {
						alert('Случилась непредвиденная ошибка');
					});
			});
		});
	</script>
</body>
</html>
