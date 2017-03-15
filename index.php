<?php

?>
<!DOCTYPE html>
<html dir=ltr lang=en>
<head>
<meta charset=utf-8>
<meta http-equiv=X-UA-Compatible content="IE=edge">
<title>EPub</title>
</head>
 
<body>

    
<h1>Bouquins</h1>

<?php
ini_set('max_execution_time', 2400);


include('inc/config.php');
include('inc/epub.php');
include('inc/util.php');

$tableau_erreurs = '';

function print_epub($path)
{

$epub = new EPub(utf8_encode($path));

$sauth = '';

foreach ($epub->Authors() as $auth)
{
$sauth .= $auth;
}

echo '<h2>Titre</h2>' . "<br />";
echo $epub->Title() . "<br />";
echo '<h3>Auteur(s)</h3>' . "<br />";
echo $sauth . "<br />";
echo "<br />";
echo '<h3>ISBN</h3>' . "<br />";
echo $epub->ISBN() . "<br />";
echo '<h3>&eacute;diteur</h3>' . "<br />";
echo $epub->Publisher() . "<br />";
echo "<br />";
echo '<h2>Description</h2>' . "<br />";
echo html_entity_decode($epub->Description()) . "<br />";
echo "<br />";
echo "<br />";

}

function epub_import($path)
{

try
{
	$epub = new EPub(utf8_encode($path));

	$name = '';
	$path_parts = pathinfo($path);
	$name = $path_parts['basename'];

	$mysqli = new mysqli("localhost", "root", "Kaorutabrisange9", "ebooks");

	$escaped_name = $mysqli->real_escape_string($name);
	$sql_query = "SELECT count(*) as cn FROM ebooks.import_ebooks where path = '" . $escaped_name . "';";

	$result = $mysqli->query($sql_query);

	$row = $result->fetch_assoc();

	if($row['cn'] == 0 )
	{
	$sauth = '';

	echo "Fichier: " . utf8_encode($path) . "<br />";
	echo "Traitement en cours... <br />";

	foreach ($epub->Authors() as $auth)
	{
	$sauth .= $auth;
	}

	$escaped_desc = $mysqli->real_escape_string(html_entity_decode($epub->Description()));

	$sql = 'INSERT INTO `ebooks`.`import_ebooks` (`name`,`author`,`description`,`publisher`,`language`,`ISBN`,`path`)
	VALUES(
	"' . utf8_decode($epub->Title()) . '",
	"' . $sauth . '",
	"' . utf8_decode($escaped_desc) . '",
	"' . utf8_decode($epub->Publisher()) . '",
	"' . utf8_decode($epub->Language()) . '",
	"' . utf8_decode($epub->ISBN()) . '",
	"' . utf8_decode($escaped_name) . '");';



	$mysqli->query($sql);

	$mysqli->close();
	}
} catch (Exception $e) {
	echo 'Exception re�ue : ',  $e->getMessage(), "\n";
} finally {
	$tableau_erreurs[] = utf8_encode($path);
}

}

$directory = 'D:\ebooks';
$i = 1;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));


while($it->valid())
{

	$path = utf8_decode($it->key());
	$path = $it->key();
	$len = strlen(utf8_decode($it->key()));
	
	if (!$it->isDot() && $len > 0)
	{
		
		//echo 'Path:  ' . $path . "<br /><br />\n\n";
		$path_parts = pathinfo($path);
		
		if($path_parts['extension'] == 'epub')
		{
			//echo 'Path:  ' . $path . "<br /><br />\n\n";
			epub_import($path);
			
		}
		
	}
    
	$it->next();
	
}

foreach ($tableau_erreurs as $err)
{

echo $err . "<br /><br />\n\n";

}
?>

</body>
</html>