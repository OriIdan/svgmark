<?PHP
/*
 | SVG mark
 | Place markers with links on SVG image
 | Written by: Ori Idan <ori@helicontech.co.il>
 */
$filesdir = "files/";

$svgfile = isset($_GET['file']) ? $_GET['file'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

$head = <<<EOH
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8" />
<title>SVG mark test</title>
<link type="text/css" rel="stylesheet" href="bootstrap.css">
<script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>
</head>
<body>
EOH;

class Point {
	public $x;
	public $y;
	public $title;
	public $link;
}

function AddMarks($points, $svgfile) {
	global $filesdir;

	$tmpfile = tempnam($filesdir, "svg_");
	$out = fopen($tmpfile, "w");
	$in = fopen("$filesdir$svgfile", "r");
	$state = 0;
	while(($line = fgets($in)) !== false) {
		if(preg_match("/<\/svg/", $line)) {		/* End of SVG file, write marks */
			// print "Writing marks<br />\n";
			fputs($out, "\t<g id=\"links\">\n");
			$i = 1;
			// print_r($points);
			foreach($points as $p) {
			/*	print "Point $i ";
				print_r($p);
				print "<br />\n"; */
				$link = $p->link;
				$title = $p->title;
				$x = $p->x;
				$y = $p->y;
				fputs($out, "\t\t<a xlink:href=\"$link\" target=\"_top\" xlink:title=\"$title\">\n");
				fputs($out, "\t\t<circle cx=\"$x\" cy=\"$y\" r=\"5\" fill=\"white\" stroke=\"red\" />\n");
				fputs($out, "\t\t<circle cx=\"$x\" cy=\"$y\" r=\"2\" fill=\"red\" />\n");
				fputs($out, "\t\t</a>\n");
				$i++;
			}
			fputs($out, "\t</g>\n");
			fputs($out, "</svg>\n");
			break;
		}
		if($state == 0) {
			if(preg_match("/<g id=\"links\"/", $line))
				$state = 1;
			else {
				fputs($out, $line);	
			}
		}
		if($state == 1) {
			if(preg_match("/<\/g/", $line))
				$state = 0;
		}
	}
	fclose($in);
	fclose($out);
	copy($tmpfile, "${filesdir}$svgfile");
	unlink($tmpfile);
}

print $head;

if($action == 'addfile') {
	$size = (int)$_FILES['svgfile']['size'];
	if($size) {	/* We seem to have a file */
		$tmpname = $_FILES['svgfile']['tmp_name'];
		$orgname = $_FILES['svgfile']['name'];
		$ext = strrchr($orgname, ".");
		$ext = substr($ext, 1);
		if($ext != 'svg')
			print "<h1>Error - File is not a SVG file</h1>\n";
		else {
			print "Move $tmpname to $filesdir$orgname<br>\n";
			move_uploaded_file($tmpname, "$filesdir$orgname");
			$svgfile = "$orgname";
		}
	}
}
if($svgfile == '') {	/* First stage, no file */
	print "<h1>Step 1 - Select SVG file</h1>\n";
	print "<form enctype=\"multipart/form-data\" action=\"?action=addfile\" method=\"post\">\n";
	print "<table class=\"form-actions\" style=\"width:40em\" border=\"0\"><tr>\n";
	print "<td>SVG file: </td><td><input type=\"file\" name=\"svgfile\" style=\"width:30em\" /> </td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\">\n";
	print "<input type=\"submit\" class=\"btn btn-primary\" value=\"Submit\" />\n";
	print "</td></tr></table>\n";
	print "</form>\n";
	print "</body>\n</html>\n";
	exit;
}

if($action == 'submit') {
	/* Get Points array */
	$ax = $_POST['x'];
	$ay = $_POST['y'];
	$alink = $_POST['link'];
	$atitle = $_POST['title'];
	$points = array();
	foreach($ax as $i => $x) {
		$p = new Point();
		$p->x = (double)$x;
		$p->y = (double)$ay[$i];
		$p->link = htmlspecialchars($alink[$i], ENT_QUOTES);
		if($p->link == '')
			$err = $i + 1;
		$p->title = htmlspecialchars($atitle[$i], ENT_QUOTES);
		// print "Location: " . $p->x . ", " . $p->y . ", " . $p->title . "<br />\n";
		$points[] = $p;
	}
	// print_r($points);
	if($err)
		print "<div class=\"alert alert-error\">No link given</div>\n";
	else {
		AddMarks($points, $svgfile);
	}
}

/* Open and find width and hight */
libxml_use_internal_errors(true);
$xmlstring = file_get_contents("$filesdir$svgfile");
$xmlstring = preg_replace("/xlink:(.*)=\"(.*)\"/", "$1=\"$2\"", $xmlstring);  
$xmlstring = preg_replace("/xlink:title=\"(.*)\"/", "title=\"$1\"", $xmlstring); 
$xml = simplexml_load_string($xmlstring);

// $xml = simplexml_load_file("$filesdir$svgfile");
if(!$xml) {
	print "<h1>Failed to load: $filesdir$svgfile</h1>\n";
	exit;
}
foreach($xml->attributes() as $k => $v) {
	if($k == 'width')
		$width = $v;
	if($k == 'height')
		$height = $v;
}
foreach($xml->g as $g) {
	foreach($g->attributes() as $k => $v) {
		if(($k == 'id') && ($v == 'links'))
			break;
	}
	if(($k == 'id') && ($v == 'links'))
		break;
}
$points = array();
$i = 0;
foreach($g->a as $a) {
//	$point = object();
	foreach($a->attributes() as $k => $v) {
		if($k == 'href')
			$point->link = $v;
		if($k == 'title')
			$point->title = $v;
	}
	$c = $a->circle;
//	print_r($a->circle);
	$c = $c[0];
	foreach($c->attributes() as $k => $v) {
		if($k == 'cx')
			$point->x = $v;
		if($k == 'cy')
			$point->y = $v;
	}
	$points[$i] = $point;
	$i++;
}

if($action == 'delpt') {
	$i = $_GET['index'];
	unset($points[$i]);
}

print "<table border=\"0\" width=\"100%\" dir=\"ltr\" style=\"margin-left:5px\"><tr><td>\n";
$style = "width:${width}px;height:${height}px;";
print "<div class=\"imgdiv\" style=\"$style\">\n";
print "<img id=\"imgdiv\" src=\"$filesdir$svgfile\" type=\"image/svg+xml\" />\n";
print "&nbsp;</div>\n";
print "</td><td valign=\"top\">\n";
print "<form name=\"location\" action=\"?action=submit&file=$svgfile\" method=\"post\">\n";
// print "Location: <span id=\"loc\" style=\"font-weight:bold\">0, 0</span><br />\n";
foreach($points as $i => $p) {
	$x = $p->x;
	$y = $p->y;
	$link = $p->link;
	$title = $p->title;
	$i1 = $i + 1;
	print "<strong>Mark $i1</strong>\n";
	print "<div style=\"margin-left:1em\">\n";
	print "<input type=\"hidden\" name=\"x[]\" value=\"$x\" />\n";
	print "<input type=\"hidden\" name=\"y[]\" value=\"$y\" />\n";
	print "<input type=\"hidden\" name=\"link[]\" value=\"$link\" />\n";
	print "<input type=\"hidden\" name=\"title[]\" value=\"$title\" />\n";
	print $p->title . "<br />\n";
	print $p->link . "<br />\n";
	print "Location: " . $p->x . ", " . $p->y . "\n&nbsp;";
	print "<a href=\"?action=delpt&amp;index=$i&amp;file=$svgfile\">Delete mark</a><br />\n";
	print "</div>\n";
}
print "<strong>New mark</strong>\n";
print "<div style=\"margin-left:1em\">\n";
print "<table><tr>\n";
print "<td>X: </td><td><input type=\"text\" id=\"x\" name=\"x[]\" style=\"width:3em\"></td>\n";
print "</tr><tr>\n";
print "<td>Y: </td><td><input type=\"text\" id=\"y\" name=\"y[]\" style=\"width:3em\"></td>\n";
print "</tr><tr>\n";
print "<td>Link: </td><td><input type=\"text\" name=\"link[]\"></td>\n";
print "</tr><tr>\n";
print "<td>Title: </td><td><input type=\"text\" name=\"title[]\"></td>\n";
print "</tr><tr><td colspan=\"2\" align=\"center\">\n";
print "<input type=\"submit\" class=\"btn btn-primary\" value=\"Submit\" /></td>\n";
print "</tr></table>\n";
print "</div>\n";

print "</form>\n";
print "</td></tr>\n";
print "</table>\n";
print "<script type=\"text/javascript\">\n";
print "var ofx = $('#imgdiv').offset().left;\n";
print "var ofy = $('#imgdiv').offset().top;\n";

/* print "$('#imgdiv').mousemove(function(e) {\n";
print "var x = e.pageX - ofx;\n";
print "var y = e.pageY - ofy;\n";
print "$('#loc').html(x + ', ' + y);\n";
print "});\n"; */

print "$('#imgdiv').click(function(e) {\n";
print "document.getElementById('x').value = e.pageX - ofx;\n";
print "document.getElementById('y').value = e.pageY - ofy;\n";
/* print "document.location.x.value = e.pageX - ofx;\n";
print "document.location.y.value = e.pageY - ofy;\n"; */
print "});\n";

print "</script>\n";
print "</body>\n";
print "</html>\n";

