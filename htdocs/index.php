<?php

try{
  require_once 'lib/lib.php';
  require_once 'lib/setup.php';

  // 
  switch (USE_CACHE){
  case "no":  $cache = new NO_CACHE(); break;
  case "apc":  $cache = new APC_CACHE('vim-wiki:'); break;
  case "file":  $cache = new FILE_CACHE(dirname(__FILE__).'/cache'); break;
  default: throw new Exception('BAD CACHE');
  }


  $git = new Git(GIT_REPOSITORY, $cache);

  function quote($s){ return htmlentities($s); }

function edit_form($content, $email, $comment){
  return '
	  <script src="http://'.EDIT_DOMAIN.'/vi/vi.js"></script>
	  <link rel="stylesheet" href="http://'.EDIT_DOMAIN.'/vi/vi.css" type="text/css" />

	  <link rel="stylesheet" href="vi.css" type="text/css" />
	  <a target="new" href="http://'.EDIT_DOMAIN.'/wiki/this-wiki/syntax.html">syntax of this wiki</a>
	  <form action="?vim_preview=1" method="post" accept-charset="utf-8">
	  <textarea name="content" rows="40" cols="80">'.quote($content).'</textarea>
	  <br/>
	  <a href="" onclick="editor(document.forms[0].elements.content);return false;">launch vi (tested wit firefox)</a><a href="http://gpl.internetconnection.net/vi"><small>(vi\'s homepage)</small></a> 

	  '.(isset($_SESSION['is_human'])
	  ? '<input type="hidden" name="I_am_human" value="I am human">' 
	  : '
	  <br/>
	  <label >Type <strong>I am human</strong></label><input type="text" name="I_am_human" value="I am robotic">'
	   )
	  .'
	  <br/>
	  <label>comment</label><input type="text" name="comment" value="'.quote($comment).'">
	  <br/>
	  <label>email</label><input type="text" name="email" value="'.quote($email).'">
	  <br/>
	  <input type="submit" name="action" value="preview">
	  <input type="submit" name="action" value="save">
	  </form>
	  ';
}


  function render_page($title, $html_content){
	  return '
<!doctype html>
<html>
<head>
  <title>'.quote($title).'</title>
    <meta name="robots" content="index,nofollow" />
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />

    <style type="text/css" media=screen>
    '.file_get_contents(LIB.'/style.css').'
    </style>
</head>
<body>
'.$html_content.'
</body>
</html>';
  }

  function render_page_cached($title, $content_str){
	  return render_page($title, $content_str);
  }

  function edit_page_url($path){
	  return 'http://'.EDIT_DOMAIN.'/wiki/edit?path='.urlencode($path);
  }
  function log_page_url($path = ''){
	  return 'http://'.EDIT_DOMAIN.'/wiki/log?path='.urlencode($path);
  }
  function commit_page_url($commit_or_hash){
	  return 'http://'.EDIT_DOMAIN.'/wiki/commit?commit='.urlencode($commit_or_hash);
  }

  function vim_wiki_search_form(){
    return '
	  <form action="http://'.EDIT_DOMAIN.'/wiki/search" method="get" accept-charset="utf-8" style="display:inline">
	  <input alt="regular expression on source files" type="text" name="q"/>
	  <input type="submit" name="action" value="search"/>
	  </form>
	  ';
  }

  function redirect_permanently($url){
	  Header( "HTTP/1.1 301 Moved Permanently" ); 
	  Header( "Location:  ".$url); 
	  exit();
  }

  if (d($_POST,'I_am_human') == 'I am human') $_SESSION['is_human'] = true;
  if (d($_POST,'email', '') !== '') $_SESSION['email'] = $_POST['email'];

  if (isset($_GET['test_exception'])){
	  throw new Exception('test exception');
  }

  if (!isset($_GET['page']) || empty($_GET['page'])){
	  Header( "HTTP/1.1 301 Moved Permanently" ); 
	  Header( "Location: http://".EDIT_DOMAIN.'/wiki/index.html' ); 
	  exit();
  }

  # sanitize path: only keep A-Z,0-9 - _ /, no spaces
  # this is also for security reasons
  $_GET['page'] = preg_replace('/\.html$/', '', $_GET['page']);
  $_GET['page'] = preg_replace('/[^A-Z_a-z0-9\/ -]/', '', $_GET['page']);

  if ($_GET['page'] == 'search'){
	  $log = $git->command(PATH.GIT.' grep -e '. escapeshellarg($_GET['q']).' | sed -n -e "s@vim-online-wiki-source@@p"' );
	  $html = '';
	  foreach(explode("\n", $log) as $line){
		  $l = explode(":", $line, 2);
		  if (count($l) == 2){
			  list($file, $line) = $l;
			  $html .= sprintf('<br/><a href="http://%s/wiki/%s.html">%s</a> %s', EDIT_DOMAIN, quote($file), quote($file) ,quote($line) );
		  }
	  }
	  echo render_page('search for regex '.$_GET['q'], $html);
	  exit();
  }
  if ($_GET['page'] == 'log'){
	$path = d($_GET, 'path', '');
	$log = $git->command(GIT.' log '. ($path != '' ? escapeshellarg('vim-online-wiki-source/'.$_GET['path']) : '') );
	$html = str_replace("\n", "<br/>", quote($log));
	                                                                                                                  
	$html = preg_replace_callback('/commit (........................................)/', function($m){
	      return sprintf('<strong>commit <a href="%s">%s</a></strong>', commit_page_url($m[1]) , $m[1]);
	}, $html);
	echo render_page('changes of '.$_GET['page'].' '.$path, 
	       ( $path != ''
	       ?
	       "<p>You're seeing changes made to [/".quote($path)."] only. "
	     	 .sprintf('<a href="%s">All changes</a></p>', quote(log_page_url()))
	       ."</p>"
	         : ''
	       ).$html
	);
	                                                                                                                  
	exit();
  }
  if ($_GET['page'] == 'commit'){
	  // todo cache?
	  header("Content-type: text/plain");
	  echo $git->command(GIT.' show '. escapeshellarg($_GET['commit']));
	  exit();
  }

  if ($_GET['page'] == 'edit'){
	  $editable_page = 'http://'.EDIT_DOMAIN.'/wiki/'.$_GET['path'];
	  echo render_page('editing '.$_GET['page'], 
		  '<p>To edit this page visit the <a href="'.quote($editable_page).'" ><strong>same page [/'.quote($_GET['path']).']</strong></a>
		  at '.EDIT_DOMAIN.'.</p>
		  <p>Then <strong>append ?vim_edit=1</strong> to the page url.</p>
		  <p> Forms are not accessible by links so that bots don\'t find 
		  them.</p>'
	  );

	  exit();
  }
  if (isset($_GET['vim_edit'])){
		try {
			$page = new Page($git, $_GET['page']);
			$content = $page->content();
		} catch (Exception $e){
			$content = '';
		}
		echo render_page_cached('editing '.$_GET['page'], edit_form($content, d($_SESSION,'email', ''), ''));
  } elseif (isset($_GET['vim_preview'])){
	  // PREVIEW OR SAVE
	  if (isset($_POST['action']) && $_POST['action'] == 'save' ){
		  // SAVE, BOT check
		  if ($_POST['I_am_human'] != "I am human"){
			  $preview = Page::text_to_html($_GET['page'], $_POST['content']);
			  echo render_page('previewing '.$_GET['page'], 
				"<strong>You're a bot! RETRY!</strong>"
				.$preview.
				edit_form($_POST['content'], $_POST['email'], $_POST['comment'])
			  );
		  } else {
			  // should verify email once !!
			$page = new Page($git, $_GET['page']);

			// normalize \r\n to \n
			$page->store(str_replace("\r\n","\n", $_POST['content']), $_POST['email'], $_POST['comment']);

			// render, output
			$page = new Page($git, $_GET['page']);
			echo render_page($page->title(), $page->html_content());
		  }

	  } else {
		  // preview
		  $preview = Page::text_to_html($_GET['page'], $_POST['content']);
		  echo render_page('previewing '.$_GET['page'], 
			$preview.
			edit_form($_POST['content'], $_POST['email'], $_POST['comment'])
		  );
	  }
  } else {
	  if (file_exists($git->git_dir.'/vim-online-wiki-source/'.$_GET['page'])){
		  if (is_dir($git->git_dir.'/vim-online-wiki-source/'.$_GET['page'])){
			if (!preg_match('/\/$/', $_GET['page'])){
				redirect_permanently('http://'.EDIT_DOMAIN.'/wiki/'.$_GET['page'].'/');
			}
			$links = '';
			foreach (glob($git->git_dir.'/vim-online-wiki-source/'.$_GET['page'].'/*') as $file){
				$links .= sprintf('<a href="%s">%s</a><br/>', quote(basename($file)), quote(basename($file)));
			}
			$title = 'conents of '.$_GET['page'];
			echo render_page_cached($title, 
				sprintf('<h1>%s</h1>', quote($title))
				.$links);
		  } else {
			 // show page
			$page = new Page($git, $_GET['page']);
			echo render_page_cached($page->title(), $page->html_content());
		  }
	  } else  {
		  // 404
		  header("HTTP/1.0 404 Not Found");
		  echo render_page('Page '.$_GET['page'].' missing, create it?',
			  sprintf(
			  '<p>Sorry, [404], This page does not exist yet.</p>
			  <p>You can <a href="%s">create it</a>.</p>', edit_page_url($_GET['page'])
		  )
		  );
	  }
  }


  if (isset($db))
	$db->execute('COMMIT');
} catch (Exception $e) {
  if (isset($db)){
    try {
      $db->execute('ROLLBACK');
    } catch (Exception $e_rollback) {
    }
  }
  uncaught_exception($e);
}
