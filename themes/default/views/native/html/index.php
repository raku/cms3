<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="/themes/default/css/main.css" type="text/css" />
	<!-- top -->
	<cms3:include renderer="document" name="header" priority="-100" />
</head>
<body>

<cms3:include renderer="message" />

<div id="menu" class="block menu-block">
<cms3:include renderer="block" position="left" template="dashed" />
</div>
<div id="content">
	<cms3:include renderer="template" name="page" />
</div>

<div id="footer">&copy; 2010 CMS 3.0 [phptal]</div>
</body>
</html>
