		<h1><?php echo $username; ?></h1>
<?php
	foreach($user_identities as $identity): ?>

		<dl>
			<dt>OpenID</dt>
			<dd><?php echo $identity->claimed_id; ?></dd>
			<dt>Email</dt>
			<dd><?php echo $identity->email; ?></dd>
		</dl>
<?php
	endforeach; ?>

	<a href="../logout">logout</a>
