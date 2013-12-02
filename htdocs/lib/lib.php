<?php

define('APC_PREFIX', 'vim-wiki:');

function my_exec($cmd){
	$handle = popen($cmd.' 2>&1', 'r');
	$s = '';
	while(!feof($handle)) 
		$s .= fread($handle, 1024); 
	if (0 !== pclose($handle))
		throw new Exception('command failed, non zero exit code'.( DEBUG ? $cmd ."\nresult:".$s: ''));
	return $s;
}

class NO_CACHE {
	function __construct(){ }

	function fetch($key, &$success){ $success = false; }

	function fetch_or_set($key, $set, $ttl = 0){
		return $set();
	}

	function store($key, $content, $ttl = 0){
		/* NOP */
	}
}

// memory cached, thread safe, see PHP
// file cache could be use instead
class APC_CACHE {
	function __construct($prefix){
		$this->prefix =$prefix;
	}

	function fetch($key, &$success){
		return apc_fetch($key, $success);
	}

	function fetch_or_set($key, $set, $ttl = 0){
		$r = $this->fetch($key, $success);
		if (!$success){
			$r = $set();
			$tihs->store($key,  $r, $ttl);
		}
		return $r;
	}

	function store($key, $content, $ttl = 0){
		return apc_store($key, $content);
	}
}


class FILE_CACHE {
	function __construct($cache_dir){
		$this->cache_dir =$cache_dir;
	}


	function fetch($key, &$success){
		$p = $this->cache_dir .'/'.md5(serialize($key));
		$success = false;
		if (file_exists($p)){
			$a = unserialize(file_get_contents($p));
			if ($a['timestamp'] >= time()){
				$success = true;
				return $a['item'];
			}
		}
	}

	function fetch_or_set($key, $set, $ttl = 0){
		$r = $this->fetch($key, $success);
		if (!$success){
			$r = $set();
			$this->store($key,  $r, $ttl);
		}
		return $r;
	}

	function store($key, $content, $ttl = 0){
		$p = $this->cache_dir .'/'.md5(serialize($key));
		file_put_contents($p, serialize(array(
			'timestamp' => time() + $ttl,
			'key' => $key,
			'item' => $content
		)));
	}
}

/* poor mans git implemntation: retrieve file contents, commit new file
 * probably libgit should be used instead, but its not commonly used
 * */
class Git {

	function __construct($git_dir, $cache){
		$this->git_dir = $git_dir;
		$this->cache = $cache;
	}

	// store retrieve file
	public function store($path, $contents, $email, $comment){
		# yes, race conditions possible. TODO: use database to sync 
		# (SELECT FOR UPDATE) or such?
		# There are maybe 9 edits a day, very unlikely to have collisions
		$file_path = $this->git_dir.'/'.$path;
		$this->command(MKDIR_PATH.' -p '.escapeshellarg(dirname($file_path)));
		file_put_contents($file_path, $contents);
		$this->command(GIT.' add '.escapeshellarg($path)); # TODO: test this
		$this->command(GIT.' commit -m '.escapeshellarg($comment."\nby ".$email.' - online'));

		$this->cache->store('rev', $this->get_rev_slow() , 60 * 10);
	}

	public function retrieve_slow($hash, $path){
		// var_export($path);
		// var_export($hash);
		return $this->command(GIT." show ".escapeshellarg($hash).':'.escapeshellarg($path));
	}
	public function retrieve_cached($path){
		$t = $this;
		return $this->cache->fetch_or_set($this->rev_cached().$path, function()use($t, $path){
			return $t->retrieve_slow($t->rev_cached(), $path);
		}, 0);
	}

	// helper
	public function get_rev_slow(){
		return preg_replace('/[^a-z0-9A-Z]/','', $this->command(GIT.' rev-parse HEAD'));
	}

	public function rev_cached(){
		$t = $this;
		$set_rev = function()use(&$t){ return $t->get_rev_slow(); };
		return $this->cache->fetch_or_set('rev', $set_rev, 60 * 10);
	}

	public function command($cmd_in_git_dir){
		return my_exec('cd '.$this->git_dir.'; '.$cmd_in_git_dir);
	}
}

class Page {

	function __construct($git, $path){
		$this->git = $git;
		$this->path = $path;
	}

	static public function text_to_html($path, $text){
		global $to_html;
		require_once LIB.'/to_html.php';
		$wiki_to_html = new WikiToHTML('wiki/');
		return $wiki_to_html->wiki_to_html($path, $text, $to_html);
	}

	function store($content, $email, $comment){
		return $this->git->store('vim-online-wiki-source/'.$this->path, $content, $email, $comment);
	}

	function content(){
		// to be edited by user
                return  (defined('USE_FILE_ON_DISK') && USE_FILE_ON_DISK === true)
                        ? file_get_contents($this->git->git_dir.'/vim-online-wiki-source/'.$this->path)
                        : $this->git->retrieve_cached('vim-online-wiki-source/'.$this->path);
		
	}

	function html_content(){
		return self::text_to_html($this->path, $this->content());
	}

	function title(){
		return $this->path;
	}
}
