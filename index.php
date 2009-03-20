<?php


// Intellectual property invites oppressive government.
// Public domain, mpb.mail@gmail.com
// Use at your own risk!
// Version: 20070803


// Is it a bug that '#! php -c php.ini -q' works but '#! php -q -c php.ini'
// does not?  No - it is a side effect of #! concatinating all the args.


ini_set ('error_reporting', E_ALL);
ini_set ('display_errors', true);


class RecipeViewer {    // --------------------------------------- RecipeViewer


  function print_r ($mixed, $return = false) {    // ------------------ print_r
    $html = "<pre>". print_r ($mixed, true). "</pre>\n";
    if ($return)  { return $html; }
    else  { print $html; } }


  function chdir ($dir = null) {    // ---------------------------------- chdir
    chdir (dirname (__FILE__). "/$dir"); }


  function check ($path, $dir = '.') {    // ---------------------------- check
    $dir = realpath ($dir);
    if (strncmp (realpath ($path), $dir, strlen ($dir)) != 0) {
      die ('check failed: '. htmlspecialchars ($path)); } }


  function renderSearchForm () {    // ----------------------- renderSearchForm

    $name = @$_GET['name']  or  $name = @$_GET['list'];
    $search = @$_GET['search']  or  $search = 'Recipes';
    // $name = preg_replace ('/[^-^\w]*/', null, $name);
    $_name = htmlspecialchars ($name);

    $gentooButton =  is_dir ('gentoo')  ?
      "<input type=submit name='search' value='Gentoo'>"  :  null  ;

    $style[$search] = 'style="background-color:#1ff19b;"';

    @$html = <<<EOT

<table><tr><td class=searchbox>
<form action='?' method=get>
<b>Search for</b>
<input name=name size=20 value='$_name' id=focus accesskey='s'>
<b>in</b>
<input type=submit name='search' value='$search' style='display:none;'>
<input type=submit name='search' value='Recipes' $style[Recipes]>
<input type=submit name='search' value='Packages' $style[Packages]>
<input type=submit name='search' value='Descriptions' $style[Descriptions]>
$gentooButton
<br/>
<div class=changelog>
( <a href='changelog.txt'>recipe viewer changelog</a>
| <a href='feedback.php'>feedback/bug reports</a> )
</div>
</form>
</td></tr></table>
<script type='text/javascript'>
element = document.getElementById('focus');
if (element) { element.focus (); }
</script>
EOT;

    return $html; }


  function listRecent () {    // ----------------------------------- listRecent
    print $this->renderSearchForm ();

    /*
    $result = $this->db->query
      ( "select 'Recipes' as 'table', * from ".
	'   ( select  * '.
	'     from    Recipes '.
	'     order   by time desc, file desc ) '.
	'group by name limit 100;' );
    */

    // Select the 100 Programs that have the most recent recipes.
    // Only the most recent recipe of each Program will be selected.
    // (I.e., If a Program has multiple recent recipes, only the
    // single most recent recipe will be listed.)

    // If a Program has multiple Recipes with the same 'time', then
    // the Recipe with the higher 'rank' will be used.

    $day = (int) (time () / 24 / 3600);
    $result = $this->db->query
      ( "select  'Recipes' as 'table', ".
	         // (time / 24 / 3600) seems to return an integer
	         // (this what we want).  SQLite has no floor
	         // function, just avg.
	"        $day - (time / 24 / 3600) as age, ".
	"        * ".
	'from    Recipes natural join '.
	'        ( select  name, max (rank) as rank, time '.
	'          from      Recipes natural join '.
	'                    ( select  name, max (time) as time '.
	'                      from    Recipes '.
	'                      group   by name '.
	'                      order   by max (time) desc '.
	'                      limit   100 ) '.
	'          group   by name ) '.
	'order   by age asc, name asc ;' );

    print "<h4>The 100 most recent recipes are listed below.</h4>\n";
    print "<table>\n";
    print $this->tableTitleRow ();
    while ($o = $result->fetchObject ()) {
      print $this->renderRow ($o); }
    print "</table>\n"; }


  function searchGentoo () {    // ------------------------------- searchGentoo

    $select = $this->db->prepare
      ( 'select name, category from Ebuilds where name like ? '.
	'order by name, category ;' );

    print $this->renderSearchForm ();
    print "<table>\n";
    print "<tr><td>Name</td><td>Category</td></tr>\n";
    $select->execute (array ("%$_GET[name]%"));
    while ($o = $select->fetchObject ()) {
      print
	"<tr><td><a href='gentoo/$o->category/$o->name/'>$o->name</a></td>".
	"<td><a href='gentoo/$o->category/'>$o->category</a></td></tr>\n"; }
    print "</table>\n"; }


  function search () {    // ------------------------------------------- search

    switch ($table = @$_GET['search'])
      {
      default:
      case 'Recipes':
	$table = 'Recipes';
	$select = $this->db->prepare
	  ( "select 'Recipes' as 'table', * from Recipes where name like ? ".
	    "order by name, rank desc ; " );
	break;

      case 'Packages':
	$select = $this->db->prepare
	  ( "select 'Packages' as 'table', * from Packages where name like ? ".
	    "order by name, rank desc ; " );
	break;

      case 'Descriptions':
	$select = $this->db->prepare
	  ( "select  'Recipes' as 'table', * ".
	    "from    Recipes join Descriptions ".
	    "on      (Descriptions.recipeId = Recipes.rowid) ".
	    "where   Descriptions.description like ? ".
	    "order   by name, rank desc ; " );
	break; 

      case 'Gentoo':
	return $this->searchGentoo ();
	break; }

    print $this->renderSearchForm ();
    $select->execute (array ("%$_GET[name]%"));

    while ($o = $select->fetchObject ()) {
      if ( $table == 'Recipes'  &&  $o->name == @$prev )  { continue; }
      $rows[] = $this->renderRow ($o);
      $prev = $o->name; }

    if ( ($count = sizeof (@$rows)) == 0) {
      if (in_array ($table, array ('Recipes', 'Descriptions'))) {
	print "<h4>Zero programs found.</h4>\n"; }
      else  { print "<h4>Zero packages found.</h4>\n"; } }

    else {
      if (in_array ($table, array ('Recipes', 'Descriptions'))) {
	print "<p style='color:#888'>$count programs found.  ";
	print "Select a program to see all versions.</p>\n";
	print "</p>\n"; }
      else {
	print "<p style='color:#888'>$count packages found.</p>\n"; }

      print "<table>\n";
      print $this->tableTitleRow ();
      print @join ($rows);
      print "</table>\n"; } }


  function tableTitleRow () {    // ----------------------------- tableTitleRow

    $age = "<span title='Age (in days)'>Age</span>";

    switch (@$_GET['search'])
      {
      default:
      case 'Recipes':
	$html[] = '<tr class="search-title">';
	$html[] = '<td>'.                       'Program';
	$html[] = '</td><td align="right">'.    "$age";
	$html[] = '</td><td align="right">'.    'Size';
	$html[] = '</td><td>'.                  'By';
	$html[] = '</td><td>'.                  'WWW';
	$html[] = '</td><td width="90%">'.      'Summary';
	$html[] = "</td></tr>\n";
	break;

      case 'Packages':
	$html[] = '<tr class="search-title">';
	$html[] = '<td>'.                       'Program';
	$html[] = '</td><td>'.                  'Platform';
	$html[] = '</td><td>';                  // Get
	$html[] = '</td><td align="right">'.    "$age";
	$html[] = '</td><td align="right">'.    'Size';
	$html[] = '</td><td>'.                  'Provider';
	$html[] = "</td></tr>\n";
	break; }
	
    return join ($html); }


  function listVersions () {    // ------------------------------- listVersions
    print $this->renderSearchForm ();
    $select = $this->db->prepare
      ( "select 'Recipes' as 'table', * from Recipes where name = ? ".
	'order by rank desc' );
    $select->execute (array ($name = $_GET['list']));

    $count = 0;  $rows = array ();
    while ($o = $select->fetchObject ()) {
      $count++;
      if ( $o->version  &&  $o->version == @$_GET['ver'] ) {
	$rows[] = $this->renderRecipeContents ($o); }
      else  { $rows[] = $this->renderRow ($o); } }

    $name = htmlspecialchars ($name);
    print "<p style='color:#888'>$count versions of $name.</p>";
    print "<table>\n";
    print $this->tableTitleRow ();
    print join ($rows);
    print "</table>\n"; }


  function completeRecipe ($o) {    // ------------------------- completeRecipe

    $o->url = "recipes/$o->file";
    $o->cacheDir = "cache/$o->name/$o->version";

    $o->ht_name =
      "<a class='RecipeTitle' href='?list=$o->ur_name&ver=$o->version'>".
      "$o->name</a>&nbsp;".
      "<a class='version' href='?list=$o->ur_name&ver=$o->version&".
      "file=Recipe'>$o->version</a>";

    $o->ht_bz2 = "<a class='RecipeLink' href='$o->url'>.bz2</a>";

    $author = htmlspecialchars ($o->author);
    if (strlen ($abbr = trim ($o->author)) > 4) {
      $abbr = substr ($abbr, 0, 4). '...'; }
    $abbr = htmlspecialchars ($abbr);
    $o->ht_auth = "<span title='$author'>$abbr</span>";

    $o->ht_links = '';
    $o->ht_links .=  $o->homepage  ?
      "<a href='$o->homepage'>www</a>"  :  null  ;

    /*
    $o->ht_links .=
      "<a href='http://gobolinux.org/websvn/dir.php?".
      "repname=recipes&path=/revisions/$o->name/$o->version/'".
      ">&nbsp;s&nbsp;</a>";
    $o->ht_links .= "<a href='$o->url'>&nbsp;z&nbsp;</a>";
    */

    if ($o->summary) {
      $href =
	"href='?list=$o->ur_name&ver=$o->version&file=Resources/Description'";
      $o->ht_summary = htmlspecialchars (substr ($o->summary, 0, 80));
      $o->ht_summary = "<div class=summary>$o->ht_summary</div>";
      $o->ht_summary = "<a $href>$o->ht_summary</a>"; }
    else  { $o->ht_summary = '(none)'; }

    return $o; }


  function completePackage ($o) {    // ----------------------- completePackage

    if (($href = $o->source) == 'official') {
      $href = 'http://gobo.calica.com/packages/official/'; }

    $o->ht_name = array_pop (explode ('/', $o->name)). " $o->version";
    $o->ht_bz2 = "<a href='$href$o->file'>bz2</a>";
    $o->ht_source = "<a href='$href'>$href</a>";
    return $o; }


  function complete ($o) {    // ------------------------------------- complete

    static $time;  $time or $time = time ();

    if (!isset ($o->age)) {
      $o->age = ((int)($time/24/3600)) - ((int)($o->time/24/3600)); }

    preg_match ('/^(.*?)--(.*?)--(.*?).tar.bz2$/', $o->file, $matches);

    $o->ht_platform = $matches[3];
    $o->ht_age = (int) $o->age;
    $o->ht_size = "&nbsp;$o->size";

    $o->ur_name = urlencode ($o->name);

    switch ($o->table) 
      {
      case 'Recipes':   return $this->completeRecipe  ($o);
      case 'Packages':  return $this->completePackage ($o);
      default:  die ('dying in default case'); } }


  function renderRowRecipe ($o) {    // ----------------------- renderRowRecipe
    $o = $this->complete ($o);
    $html[] = "<tr><td>\n".                 "$o->ht_name";
    $html[] = "</td><td align=right>\n".    "$o->ht_age";
    $html[] = "</td><td align=right>\n".    "$o->ht_size";
    $html[] = "</td><td>\n".                "$o->ht_auth";
    $html[] = "</td><td class=links>\n".    "$o->ht_links";
    $html[] = "</td><td>\n".                "$o->ht_summary";
    $html[] = "</td></tr>\n";
    return join ($html); }


  function renderRowPackage ($o) {    // --------------------- renderRowPackage
    $o = $this->complete ($o);
    $html[] = "<tr><td>\n".                 "$o->ht_name";
    $html[] = "</td><td align=center>\n".   "$o->ht_platform";
    $html[] = "</td><td>\n".                "$o->ht_bz2";
    $html[] = "</td><td align=right>\n".    "$o->ht_age";
    $html[] = "</td><td align=right>\n".    "$o->ht_size";
    $html[] = "</td><td>\n".                "$o->ht_source";
    $html[] = "</td></tr>\n";
    return join ($html); }


  function renderRow ($o) {    // ----------------------------------- renderRow
    switch ($o->table)
      {
      case 'Recipes':  return $this->renderRowRecipe ($o);
      case 'Packages':  return $this->renderRowPackage ($o); } }


  function crossLinkCallback ($matches) {    // ------------- crossLinkCallback
    list ($null, $name, $tail) = $matches;
    $ur_name = urlencode ($name);
    return "<a href='?list=$ur_name'>$name</a>$tail"; }


  function crossLink ($data, $filename) {    // --------------------- crossLink

    switch ($filename)
      {
      case 'Resources/BuildInformation':
      case 'Resources/Dependencies':
      case 'Resources/BuildDependencies':
	$pattern = '/^(\S+)(.*)$/m';
	$replace = "<a href='?list=$1'>$1</a>$2";
	// $data = preg_replace ($pattern, $replace, $data);
	$callback = array ($this, 'crossLinkCallback');
	$data = preg_replace_callback ($pattern, $callback , $data);
	break; }

    return $data; }


  function renderRecipeContents ($info) {    // ---------- renderRecipeContents

    $info = $this->complete ($info);
    if (!is_dir ($info->cacheDir)) {
      if (!is_dir ('cache'))  { mkdir ('cache', 0755); }
      $this->check ("recipes/$info->file", 'recipes');
      exec ("tar xjf recipes/$info->file -C cache"); }

    exec ("find $info->cacheDir", $find);
    sort ($find);
    foreach ($find as $file) {
      $file = substr ($file, strlen ($info->cacheDir)+1);

      if ( $file  &&  is_file ("$info->cacheDir/$file") ) {
	$href = "?list=$info->ur_name&ver=$info->version&file=$file";
	if ($file == @$_GET['file']) {
	  $files[] = "<a href='$href' class=SelectedFileLink>$file</a>\n";
	  $path = "$info->cacheDir/$file";
	  $this->check ($path);
	  $contents = file_get_contents ($path);
	  $contents =
	    preg_replace ('/[^\n]{80}\S*[ \t]+/', "$0\\\n", $contents);
	  $contents = htmlspecialchars ($contents);
	  $contents = $this->crossLink ($contents, $file);
	  if (!trim ($contents)) { $contents = "(empty file)"; }
	  $contents = "<pre class=FileContents>$contents</pre>\n"; }
	else {
	  $files[] = "<a href='$href' class=FileLink>$file</a>\n"; } } }

    $html[] = $this->renderRow ($info);
    $html[] = "<tr><td colspan=7 style='padding-left:60px;'>\n";

    $href = "http://gobolinux.org/websvn/dir.php?".
      "repname=recipes&path=/revisions/$info->name/$info->version/";
    $html[] = "<a href='$href'>view subversion entry</a> |\n";
    $html[] = "<a href='$info->url'>download recipe.bz2 file</a>\n";

    $html[] = "</td></tr><tr><td colspan=7 style='padding-left:60px;'>\n";
    // $html[] = $this->print_r ($info, true);
    $html[] = "<pre>";
    $html[] = join ($files);
    $html[] = "</pre>";
    $html[] = @$contents;
    $html[] = "</td></tr>\n"; 
    return join ($html); }


  function pageHeader () {    // ----------------------------------- pageHeader

    $list = htmlspecialchars (@$_GET['list']);
    $name = htmlspecialchars (@$_GET['name']);
    $ver  = htmlspecialchars (@$_GET['ver']);

    if ($list)      { $title = "$list $ver - "; }
    elseif ($name)  { $title = "$name - "; }

    @$title .= 'GoboLinux Recipe Viewer';

    $html = <<<EOT
<html>
<head>
<title>$title</title>
<link rel="stylesheet" type="text/css" href="2006.css"/>
<link rel='shortcut icon' href='favicon.ico' type='image/x-icon' />
</head>
<body>
EOT;
    print $html;

    if (is_file ('titlebar.php'))  { include ('titlebar.php'); } }


  function pageFooter () {    // ----------------------------------- pageFooter
    print "</body>\n</html>\n"; }


  function http () {    // ----------------------------------------------- http

    $this->db = new PDO ('sqlite:sqlite.db');
    $this->db->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

    $this->phpSelf = $_SERVER['PHP_SELF'];

    if (@$_GET['list'])  { $this->listVersions (); }

    elseif ( @$_GET['name']  ||
	     @$_GET['search'] == 'Packages' ) {
      $this->search (); }

    else  { $this->listRecent (); } }


  function rsync2list ($source) {    // ---------------------------- rsync2list

    $this->chdir ();

    if ($source[0] == '#')  { return; }
    print "source: $source\n";

    $this->db->beginTransaction ();

    $insert = $this->db->prepare
      ( 'insert  into Packages (source, file) values (?,?) ; ' );

    $update = $this->db->prepare
      ( 'update  Packages '.
	'set     name=?, version=?, time=?, size=? '.
	'where   source=? and file=? ; ' );

    $url =  $source == 'official'  ?  'official'  :  "${source}.find.php"  ;

    $in = fopen ($url, 'r');
    while ($line = fgets ($in)) {
      $split = preg_split ('/\s+/', trim ($line));
      switch (sizeof ($split))
	{
        case 5:
	  list ($perms, $size, $date, $time, $file) = $split;
	  $time = strtotime ("$date $time");
	  break;

	case 3:
	  list ($time, $size, $file) = $split;
	  break;

	default:
	  continue 2; }

      $pattern = '#([^/]*?)--(.*?)--(\w+).tar.bz2$#';
      if (preg_match ($pattern, $file, $matches)) {
	list ($null, $name, $version, $platform) = $matches;
	$insert->execute ( array ( $source, $file ) );
	$update->execute ( array ( $name, $version, $time, $size,
				   $source, $file ) );
	;;; } }

    $this->db->commit (); }


  function pkgsync () {    // ----------------------------------------- pkgsync

    $delete = $this->db->prepare ('delete  from Packages ;');
    $delete->execute ();

    $sources =  is_file ('sources')  ?
      file ('sources')  :  array ('official') ;
    foreach ($sources as $source)  { $this->rsync2list (trim ($source)); }

    $this->setRanks (null, 'Packages'); }


  function rsync () {    // --------------------------------------------- rsync

    print "recipe viewer rsync beginning at ". date ('r'). "\n";

    $recipes = 'www.calica.com::gobolinux-recipes';
    $packages = 'www.calica.com::gobolinux-packages';

    if (is_link ('recipes')) {
      print "skipping 'recipes' (as it is a symlink, not directory)\n"; }
    else {
      if (!is_dir ('recipes'))  { mkdir ('recipes', 0755); }
      $command =
	"cd recipes  &&  ".
	"rsync -abv --delete --backup-dir=../deleted-recipes $recipes ."; 
      passthru ($command); }

    $command = "rsync $packages/official/ > official";
    passthru ($command, $ret);

    $this->dbsync ('recipes', 'r');
    $this->pkgsync ();

    print "recipe viewer rsync success at ". date ('r'). "\n"; }


  function dbcreate () {    // --------------------------------------- dbcreate

    $sql[] =
      'create table if not exists Recipes '.
      '(source, file, unique (source, file) on conflict ignore) ; ';

    $sql[] = '@alter table Recipes add column name         ; ';
    $sql[] = '@alter table Recipes add column version      ; ';
    $sql[] = '@alter table Recipes add column rank integer ; ';
    $sql[] = '@alter table Recipes add column time integer ; ';
    $sql[] = '@alter table Recipes add column size integer ; ';
    $sql[] = '@alter table Recipes add column homepage     ; ';
    $sql[] = '@alter table Recipes add column author       ; ';
    $sql[] = '@alter table Recipes add column summary      ; ';

    $sql[] =
      'create table if not exists Packages '.
      '(source, file, unique (source, file) on conflict ignore) ; ';

    $sql[] = '@alter table Packages add column name         ; ';
    $sql[] = '@alter table Packages add column version      ; ';
    $sql[] = '@alter table Packages add column platform     ; ';
    $sql[] = '@alter table Packages add column rank integer ; ';
    $sql[] = '@alter table Packages add column time integer ; ';
    $sql[] = '@alter table Packages add column size integer ; ';

    $sql[] =
      'create table if not exists Descriptions '.
      '(recipeId integer, unique (recipeId) on conflict replace) ; ';

    $sql[] = '@alter table Descriptions add column description ; ';

    $sql[] =
      'create table if not exists Ebuilds '.
      '(category, name, unique (category, name) on conflict replace) ; ';

    foreach ($sql as $s) { 
      if ($s[0] == '@')  { @$this->db->exec (substr ($s, 1)); }
      else  { $this->db->exec ($s); } } }


  function extractAge ($file, $name, $version) {    // ------------- extractAge
    // print "extract age: $file  $name  $version\n";
    $recipe = `tar xjf $file -O $name/$version/Recipe 2> /dev/null`;
    $pattern = '/, on (.*)/';
    if (preg_match ($pattern, $recipe, $matches)) {
      $time = strtotime ($matches[1]);
      // print_r ($matches);
      // print (date ('r', $time). "\n");
      return $time; }
    else {
      print "FAILED TO PARSE RECIPE TIME\n";
      print $recipe; }
    return 0; }


  function extractDescription ($file, $name, $version) {    // ----------------

    $o->author = $o->homepage = $o->summary = null;

    $path = "$name/$version/Recipe";
    $recipe = `tar xjf $file -O $path 2> /dev/null`;

    $pattern = '/^# Recipe .*? by ([^@<\n,]+)[\s,]/';
    preg_match ($pattern, $recipe, $matches) and
      $o->author = trim ($matches[1]);
    // print "\t\t\t\t$o->author\n";

    $path = "$name/$version/Resources/Description";
    $o->desc = `tar xjf $file -O $path 2> /dev/null`;

    preg_match ('/^\[Homepage]\s*(http\S+)/mi', $o->desc, $matches) and
      $o->homepage = $matches[1];

    $pattern = '/^\[(?:Summary|Description)]\s*(.{0,70}\S*)/m';
    if (preg_match ($pattern, $o->desc, $matches)) {
      $o->summary = trim ($matches[1]); }
    else {
      preg_match ('/^.{0,70}\S*/m', $o->desc, $matches) and
	$o->summary = trim ($matches[0]); }

    // print_r ($o);
    return $o; }


  function dbsync ($dir, $source) {    // ------------------------------ dbsync

    $this->dbcreate ();

    $select = $this->db->prepare
      ( 'select  file from Recipes where source=? ;' );
    $select->execute (array ($source));
    while ($file = $select->fetchColumn ())  { $files[$file] = true; }

    $this->db->beginTransaction ();

    $insert = $this->db->prepare
      ( 'insert  into Recipes (source, file) values (?,?) ;' );

    $update = $this->db->prepare
      ( 'update  Recipes '.
	'set     name=?,    version=?,   time=?,    size=?, '.
	'        author=?,  homepage=?,  summary=? '.
	'where   source=?  and  file=? ;' );

    $this->chdir ($dir);
    $glob = glob ('*');
    // $glob = glob ('Subve*');
    foreach ($glob as $file) {
      if (@$files[$file])  { continue; }
      print "new file: $file\n";
      $pattern = '#^(.*?)--(.*?)--recipe.tar.bz2$#';
      if (preg_match ($pattern, $file, $matches)) {
	list ($null, $name, $version) = $matches;

	$time = filemtime ($file);
	// $time = $this->extractAge ($file, $name, $version);

	$size = filesize ($file);
	$o = $this->extractDescription ($file, $name, $version);
	$insert->execute ( array ( $source, $file ) );
	$update->execute ( array ( $name, $version, $time, $size,
				   $o->author, $o->homepage, $o->summary,
				   $source, $file ) );
	$newNames[$name] = $name;
	if ( (@$loop++ + 1) % 300 == 0 ) {
	  $this->db->commit ();  $this->db->beginTransaction (); } } }

    $this->db->commit ();
    $this->chdir ();

    $this->dbclean ();
    if (@$newNames) {
      $this->setRanks ($newNames);
      $this->setDescriptions ($newNames); }

    $this->dbgentoo (); }


  function dbclean () {    // ----------------------------------------- dbclean
    $select = $this->db->query
      ( 'select rowid, name, version from Recipes ;' );
    while ($r = $select->fetchObject ()) {
      $path = "recipes/$r->name--$r->version--recipe.tar.bz2";
      if (!is_file ($path)) {
	printf ("delete %5d %s\n", $r->rowid, $path);
	$deleteIds[] = (int) $r->rowid; } }

    if (@$deleteIds) {
      $deleteIds = join (',', $deleteIds);
      $this->db->query ("delete from Recipes where rowid in ($deleteIds) ;");
      $this->db->query
	("delete from Descriptions where recipeId in ($deleteIds) ;"); } }


  function dbgentoo () {    // --------------------------------------- dbgentoo

    $this->chdir ();
    if (!is_dir ('gentoo'))  { return; }
    $this->chdir ('gentoo');

    $insert = $this->db->prepare
      ( 'insert  into Ebuilds (category, name) values (?,?) ;' );

    $glob0 = glob ('*');
    $this->db->beginTransaction ();
    foreach ($glob0 as $category) {
      $glob1 = glob ("$category/*");
      foreach ($glob1 as $name) {
	// print "$name\n";
	list ($null, $name) = explode ('/', $name);
	$insert->execute (array ($category, $name));
	
	if ( (@$loop++ + 1) % 100 == 0 ) {
	  $this->db->commit ();  $this->db->beginTransaction (); } } }

    $this->db->commit (); }


  function setRanksReplace ($d) {    // ----------------------- setRanksReplace
    return sprintf ("%010d", $d[0]); }


  function setRanksCmp ($r0, $r1) {    // ------------------------- setRanksCmp
    return strcmp ($r0->version_x, $r1->version_x); }


  function setRanks ($names = null, $table = 'Recipes') {    // ------ setRanks

    if ($names === null) {
      $select = $this->db->query ("select distinct name from $table;");
      while ($name = $select->fetchColumn ())  { $names[] = $name; } }

    // $names = array ('Firefox');

    $this->db->beginTransaction ();
    foreach ($names as $name) {

      // print "set rank: $name\n";

      $select = $this->db->prepare
	( 'select  rowid, name, version, rank '.
	  "from    $table where name=? ".
	  'order   by rank ;' );
      $select->execute (array ($name));

      unset ($recipes);
      while ($r = $select->fetchObject ()) {
	// printf ("%s %3d %s\n", $r->name, $r->rank, $r->version);
	$callback = array ($this, 'setRanksReplace');
	$r->version_x = preg_replace_callback
	  ( '/\d+/', $callback, $r->version );
	        // finesse 'rc'
	$r->version_x = strtr ($r->version_x, array ('rc' => '#'));
	$recipes[] = $r; }

      $callback = array ($this, 'setRanksCmp');
      usort ($recipes, $callback);
      // foreach ($recipes as $r)  { print "$r->version\n"; }

      $rank = 0;
      $update = $this->db->prepare
	( "update $table set rank=? where rowid=? ;" );
      foreach ($recipes as $r) {
	$update->execute (array ($rank++, $r->rowid)); }

      if ( (@$loop++ + 1) % 50 == 0 ) {
	$this->db->commit ();  $this->db->beginTransaction (); } }

    $this->db->commit (); }


  function setDescriptions ($names = null) {    // ------------ setDescriptions

    $this->db->beginTransaction ();

    if ($names === null) {
      $select = $this->db->query ('select distinct name from Recipes;');
      while ($name = $select->fetchColumn ())  { $names[] = $name; } }

    $select = $this->db->prepare
      ( 'select  rowid, name, version, rank '.
	'from    Recipes '.
	'where   name=? '.
	'order   by rank desc limit 1 ;' );
    $insert = $this->db->prepare
      ( 'insert  into Descriptions (description, recipeId) values (?,?) ; ' );

    foreach ($names as $name) {
      print "set desc: $name\n";
      $select->execute (array ($name));
      if ($r = $select->fetchObject ()) {
	$path = "recipes/$r->name--$r->version--recipe.tar.bz2";
	if (is_file ($path)) {
	  $o = $this->extractDescription ($path, $r->name, $r->version);
	  if ($o->desc) {
	    $insert->execute (array ($o->desc, $r->rowid)); } } }
      $select->closeCursor ();

      if ( (@$loop++ + 1) % 50 == 0 ) {
	$this->db->commit ();  $this->db->beginTransaction (); } }

    $this->db->commit (); }


  function fixage () {    // ------------------------------------------- fixage

    $agedb = new PDO ('sqlite:fixage.db');
    $agedb->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

    $update = $this->db->prepare
      ( 'update Recipes set time = ? where name = ? and version = ?' );

    $select = $agedb->query ('select name, version, time from Recipes');
    
    $this->db->beginTransaction ();
    while ($row = $select->fetchObject ()) {
      $update->execute (array ($row->time, $row->name, $row->version));
      if (@$count++ % 100 == 0) {
	$this->db->commit ();
	$this->db->beginTransaction (); } }
    $this->db->commit (); }


  function main ($argv) {    // ------------------------------------------ argv

    $this->db = new PDO ('sqlite:sqlite.db');
    $this->db->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

    switch ($command = @$argv[1])
      {
      case 'dbclean':
      case 'dbcreate':
      case 'dbgentoo':
      case 'fixage':
      case 'pkgsync':
      case 'rsync':
      case 'setDescriptions':
      case 'setRanks':
	$this->$command (); 
	break;

      case 'dbsync':
	$this->dbsync ('recipes', 'r');
	break;

      default:
	print <<<EOT
usage: $argv[0] <command>

commands:
  dbcreate  create the sqlite.db file and create/update its schema
  dbsync    index recipes directory (also calls dbcreate)
  pkgsync   index package sources
            (default source: rsync www.calica.com::gobolinux-packages)
  rsync     pull recipes directory in from rsync www.calica.com
            (also calls dbsync and pkgsync)

  setDescriptions  rebuild description table
  setRanks         rebuild recipe version rankings

Note: you can easily rebuild the database from scratch as follows:
  rm sqlite.db
  $argv[0] dbsync
  $argv[0] pkgsync

EOT;
	exit (1);
	break; } }


}    // end class RecipeViewer ------------------------- end class RecipeViewer


if (@$_SERVER['GATEWAY_INTERFACE']) {
  if (__FILE__ == realpath ($_SERVER['SCRIPT_FILENAME'])) {
    $viewer = new RecipeViewer;
    $viewer->pageHeader ();
    $viewer->http ();
    $viewer->pageFooter (); } }

elseif (__FILE__ == realpath ($_SERVER['argv'][0])) {
  ini_set ('max_execution_time', 0);
  $viewer = new RecipeViewer;
  $viewer->main ($_SERVER['argv']); }


?>