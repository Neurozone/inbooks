<?php


/**
 * eKatab generates a jQuery Mobile site from an ePub file (zip arcive)
 * it's really that simple.
 * requires PHP 5.4
 * @package eKatab
 * @author Karl Holz
 *
 */
class eKatab  {
	
	// must match in zipfile
	/**
	 * This file must exist int the epub file!!
	 *
	 * @var string $container this file is relitive to the ebook root
	 */
	private $container='META-INF/container.xml';
	
	/**
	 * File that contains digest signatures
	 *
	 * @var string $signatures this file is relitive to the ebook root
	 */
	private $signatures='META-INF/signatures.xml';

	/**
	 * File that contains encryped file references
	 *
	 * @var string $encryption this file is relitive to the ebook root
	 */
	private $encryption='META-INF/encryption.xml';
	
	/**
	 * Fairplay related xml data
	 *
	 * @var string $sinf this file is relitive to the ebook root
	 */
	private $sinf='META-INF/sinf.xml';
	
	/**
	 * This is the itunes plist file, if it's found then it will be prarced
	 *
	 * @var string $plist name of the plist file to match
	 */
	private $plist='iTunesMetadata.plist';
	
	/**
	 * This is the itunes art work cover
	 *
	 * @var string $cover jpeg cover page
	 */
	private $cover='iTunesArtwork';

	/**
	 * this is the ebooks configuration file, it is generated from an opf file
	 *   - this is the name of this books configuration 
	 * @var string $opf
	 */
	private $opf = '/opf.ini';

  /**
   * @var $mime_type
   */
	private $mime_type;
	
	/**
	 * 
	 * @var array $book_info
	 */
	public $book_info=array();
	/**
	 *
	 * @var array $manifest
	 */
	protected $manifest=array();
	
	/**
	 *  files found in the zip arcive
	 */
	protected $scanned_files=array();
	/**
	 *
	 * @var array $layout
	 */
	protected $layout=array();
	
	/**
	 *
	 * @var string $toc
	 */
	protected $toc;	
	/**
	 * list of supported mimetypes
	 * @var array $types
	 */
	private $types=array(
		'application/epub+zip',
		'application/x-ibooks+zip'
	);

	/**
	 * This will autoload the epub file and accept requests to it via PATH_INFO / $this->rest
	 * @param file $epub epub file
	 * @param string $prefix prefix to epub root in zipfile
	 */
	function __construct($epub) {
	
	//$this->debug=TRUE;
	
		$this->prefix='';
		//set auto env values
		$this->auto_invoke();
		if (is_file($this->controller_root.'/'.$epub)) {
			$this->load_book($this->controller_root.'/'.$epub);
			$this->send_view();
			
		} elseif (is_file($epub)) {
			$this->load_book($epub);
			$this->send_view();
			
		} else {
			$this->error=$epub.' |'.__METHOD__.' | '.__LINE__;
			$this->error('not_file');
		}

	}
	
	/**
	 * check if the rest string needs to be re-encoded as raw url
	 * @param array $list - check if the request url matches an item in this manifest
	 * @return string
	 */
	function check_rest($list) {
		$rest=ltrim($this->rest, '/');
		if ($rest == '') return '/';
		//test one
		if (in_array($rest, $list)) return $rest;
		//test two decode all first
		$rest=urldecode($rest);
		if (in_array($rest, $list)) return $rest;
		//test 3 re-rawurlencode  
		$r=explode('/', $this->rest);
		$r2=array();
		array_shift($r);
		foreach ($r as $v) {
			$r2[]=rawurlencode($v);
		}
		$rest=join('/', $r2);
		if (in_array($rest, $list)) return $rest;
		$this->error=$rest.' |'.__METHOD__.' | '.__LINE__.'<br />';
		$this->error('not_found');
		
	}
	
	/**
	 * This will print out the requested resource to the browser
	 */
	function send_view() {
		$rest=$this->check_rest($this->ini['manifest']['href']);
		if (in_array($rest, $this->ini['manifest']['href'])) {
			$m=array_search($rest, $this->ini['manifest']['href']);
			if ($this->ini['manifest']['type'][$m] == 'application/xhtml+xml') {
				
				$this->load_html_page($this->ini['manifest']['zip'][$m], $this->ini['manifest']['href'], $this->ini['manifest']['rest']);
			} else {
				$f=file_get_contents($this->ini['manifest']['zip'][$m]);
				echo $f;
				exit();
			}
		} elseif ($this->rest == '/' || $this->rest == '') {
		  if (in_array($this->cover, $this->scanned_files) && function_exists('getimagesizefromstring')) {
		   $imgbinary = file_get_contents($this->zip.'#'.$this->cover);
		   list($this->cover_width, $this->cover_height, $this->cover_type, $this->cover_attr) = getimagesizefromstring($imgbinary); // php 5.4.0
		   $this->html='<img src="data:image/jpg;base64,'.base64_encode($imgbinary).'" />';
		  } else {
			 $this->load_html_page($this->book_info['first'], $this->ini['manifest']['href'], $this->ini['manifest']['rest']);
			}
		} elseif ($this->rest == '/style.css') { 
			$this->epub_style();
		} elseif ($this->rest == '/manifest') {
			$this->cache_manifest($this->ini['manifest']['rest']);
		} else {
			$this->error=$this->rest.' |'.__METHOD__.' | '.__LINE__.'<br /><pre>'.print_r($this, TRUE).'</pre><pre>'.print_r($_SERVER, TRUE).'</pre>';
			$this->error('not_found');
		}
		echo $this;
	}
	
	/**
	 * Icons for mobile app 
	 * @var array $icon
	 */
	private $icon=array(
		'iphone' => array('320', '460'),
		'ipad_p' => array('748', '1004'),
		'ipad_l' => array('748', '1024')
	);
	
	function get_icon($type='iphone') {
		if (in_array($type)) {
			$size=$this->icon[$type];
		} else {
			$size=$this->icon['iphone'];
		}
		
	}
	/**
	 * load and fix html pages in the ebook before sending it to the browser
	 * @param string $file can be any file referance that is supported by file_get_contents($x), i'm using mostly zip://
	 * @param array $href a list of urls (or words, names, emails, etc.  just note that it needs to be supported by str_replace) to search for within the html documnent
	 * @param array $rest a list of new url or words, names, emails, etc. to replace the pervious list
	 */
	function load_html_page($file, $href=array(), $rest=array()) {
		if (!in_array($file, $this->scanned_files)) $file=urldecode($file);
		if ($this->crypt) {
			$this->html='<h1>Encryped Book</h1>';
		} else {
			$f=file_get_contents($file);
			$this->error=$file.' |'.__METHOD__.' | '.__LINE__;
			if (! $f) $f=file_get_contents(rawurldecode($file));
			if (! $f) $this->error('no_rest_zip_match');
			$h=$this->xsl_out($this->html_xsl(), $f);
			$this->html=str_replace($href, $rest, $h);
		}
	}
	
	/**
	 * reads the container file for the opf file location in the loaded epub or ibooks zip file
	 * @return string
	 */
	function read_container() {
		$container=file_get_contents($this->zip.'#'.$this->prefix.$this->container);
		$this->error=$this->zip.'#'.$this->prefix.$this->container.' |'.__METHOD__.' | '.__LINE__;
		if (!$container) $this->error('not_zip'); //get opf file
		return $this->xsl_out($this->container_xsl(), $container);
	}
	
	/**
	 * parces the opf file returned by the read_container and creates a configuration for the current ebook
	 * @param $epub epub doc
	 * @param $opf opf file that contains the ebooks hypermedia links and mimetypes
	 * @return array parsed ini string
	 */
	function read_opf($epub, $opf) {
		if (!is_dir($this->controller_root.'/data/')) mkdir($this->controller_root.'/data/');
		$this->opf='/data/'.basename($epub).'.opf.ini';
		if (!is_file($this->controller_root.$this->opf)) {
			$ini=file_get_contents($this->zip.'#'.$this->prefix.$opf);
			$this->error=$this->zip.'#'.$this->prefix.$opf.' |'.__METHOD__.' | '.__LINE__;
			if (!$ini) $this->error('no_opf_file');
			if ($this->book_root == '.' && $this->prefix='') {
				$param=array('base' => $this->base_url, 'zip' => $this->zip.'#');
			} elseif ($this->book_root == '.' ) {
				$param=array('prefix' => $this->prefix, 'base' => $this->controller, 'zip' => $this->zip.'#'.$this->prefix);
			} else {
				$param=array('prefix' => $this->prefix.$this->book_root.'/', 'base' => $this->controller, 'zip' => $this->zip.'#'.$this->prefix.$this->book_root.'/');
			}
			$ini_txt=$this->xsl_out($this->opf_xsl(), $ini, $param, $this->controller_root.$this->opf);
		}
		return parse_ini_file($this->controller_root.$this->opf, TRUE);
	}
	
	function read_mime($m){
		$f=file_get_contents($this->zip.'#'.$m);
		$this->mime_type=$f;
		if (in_array(trim($f), $this->types)) return TRUE;
		$this->error=$m.' |'.__METHOD__.' | '.__LINE__.'<br />'.$f.'<br />'.$this->zip;
		$this->error('bad_mime');
	}
	/**
	 * read the found Toc file and build ebook index for web usage
	 * @param string $t toc file
	 * @return boolean
	 */
	function read_toc($t) {
		$toc=file_get_contents($t);
		$this->error=$t.' |'.__METHOD__.' | '.__LINE__;
		if (!$toc) $this->error('not_toc');
		foreach ($this->ini['book_layout']['id'] as $x => $v) {
			$label=$this->ini['book_layout']['href'][$x];
			$search=$this->xsl_out($this->search_toc_xsl(), $toc, array('search' => $label));
			if (!$search) {
				$h=explode('.', $this->ini['book_layout']['href'][$x]);
				$e=array_pop($h);
				$search=' =&gt; '.basename($this->ini['book_layout']['href'][$x], '.'.$e);
			}
			$label=$search;
			$this->layout[]=array(
				'id' => $this->ini['book_layout']['id'][$x],
				'type' => $this->ini['book_layout']['type'][$x],
				'zip' => $this->ini['book_layout']['zip'][$x],
				'href' => $this->ini['book_layout']['href'][$x],
				'rest' => $this->ini['book_layout']['rest'][$x],
				'prev' => $this->ini['book_layout']['prev'][$x],
				'next' => $this->ini['book_layout']['next'][$x],
				'label' => $label
			);
		}
		array_shift($this->ini); // drop off layout since it's loaded
		return TRUE;
	}

	/**
	 * load the ebook file, will check the archives if a file exists or not in the zipfile
	 * @param string $epub
	 * @return boolean
	 */
	
	function load_book($epub) {
		$this->error=$epub.' |'.__METHOD__.' | '.__LINE__;
		if (! is_file($epub)) $this->error('not_file');
		$this->zip='zip://'.trim($epub);
	
		$this->scanned_files=$this->zip_resorces($epub);
		if (in_array($this->encryption, $this->scanned_files)) {
			$this->crypt=TRUE;
			
		}
		if (in_array($this->signatures, $this->scanned_files)) {
				
				
		}
		foreach ($this->scanned_files as $z) {
			//META-INF/container.xml
			if (preg_match('#'.$this->container.'$#', $z)) {
				$this->prefix=str_replace($this->container, '', $z);
			} elseif (preg_match('#'.$this->plist.'$#', $z)) {
				$this->itunes=$z;
			} elseif (preg_match('#mimetype$#', $z)) {// lookup mime type
				$this->read_mime($z);
			}			
		}
		$opf=$this->read_container(); 
		$this->book_root=dirname($opf);
		$this->ini=$this->read_opf($epub, $opf);
		$this->book_info=array_shift($this->ini);
		$this->read_toc($this->book_info['toc']);
	
		return TRUE;
	}
	
	/**
	 * cache manifest document for a more webapplike experience and offline access
	 * @param array $man list of files to download
	 */
	function cache_manifest($man) {
		$list=join("\n", $man);
		header('Content-Type: text/cache-manifest');
		$m=<<<m
CACHE MANIFEST:
		
#jQuery css, need to replace with css-one
http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.css
# jQuery and jQuery Mobile
http://code.jquery.com/jquery-1.8.2.min.js
http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.js
# epub manifest
$list
m
		;
		echo $m;
		exit();
	}
	
	function epub_style() {
		$css=new css_one();

		
//		$css->add_style($style);
		// add custom css
		$css->add_style('/css/jquery.mobile-1.2.0.css');
		$css->printCSS();
		exit();
	}
	
	function plist_info() {
		$plist=trim(file_get_contents($this->zip.'#'.$this->itunes));
		$xml=new DOMDocument();
		$info='';
		if (preg_match('#^<plist>#i', $plist) && $xml->loadXML($plist)) {
			$info='<h3>PLIST Dump</h3>';
			$info.=$this->xsl_out($this->metadata_plist_xsl(), $plist);
		}
		unset($xml);
		return $info;
	}
	
	function __toString() {
		$title=$this->book_info['title'];
		$sec=$title;
		$base=$this->base_url.'/';

		$rest=$this->check_rest($this->ini['manifest']['href']);
		$p='';
		$n='';
		$info='';
    	$style='';
		$debug='';
		$css='<link rel="stylesheet" href="css/jquery.mobile-1.2.0.css" />';
		$links='<ul data-role="listview" id="page_links" data-filter="true"  data-inset="true">';
		foreach ($this->layout as $l => $o) {
			if ($o['href'] == $rest) {
				$p='<li><a id="prev" data-transition="slide"  href="'.$o['prev'].'" data-icon="arrow-l">Prev</a></li>';
				$n='<li><a id="next" data-transition="slide" href="'.$o['next'].'" data-icon="arrow-r" >Next</a></li>';
				$sec=$o['label'];
			} 
			if ($o['type'] == 'application/xhtml+xml' && $o['zip'] != $this->book_info['first']) {
			 $links.='<li><a  id="'.$o['id'].'" href="'.$o['rest'].'"  >'.$o['label'].'</a></li>';
			}
		}
		$links.='</ul>';
		$home=$this->base_url;
		$html=$this->html;
		$rest=urldecode($rest);
		// book_info
		$creator=$this->book_info['creator'];
		$publisher=$this->book_info['publisher'];
		$desc=$this->book_info['description'];
		$subj=$this->book_info['subject'];
		$date=$this->book_info['date'];
		$lang=$this->book_info['language'];
		// manifest, in html tag manifest="$manifest"
		$manifest=$this->controller.'/manifest';
		$a=0;
		if ($this->itunes) $this->plist_info();
		$footer='';
    if ($this->mime_type == 'application/x-ibooks+zip') 
      $footer='<div data-role="footer"  data-position="fixed"><h4>iBooks Author.app books are not fully supported! it will not render properly</h4></div>';
    if ($this->crypt) $footer='<div data-role="footer"  data-position="fixed"><h4>Encryped Book</h4></div>';

    $debug='<pre>'.print_r($this, TRUE).'</pre>';
    
	if (preg_match('/^.*iPhone/', $this->client_name)) {
		$style=<<<s
img {
	width: 290px;
	height: 430px;
}
s
;
	} else {
		$h=$this->cover_height;
		$w=$this->cover_width;
		$style=<<<s
img {
	width: $w;
	height: $h;
}
s
;		
	}
	$icons='';
	// jquery mobile app icon
	if (in_array($this->cover, $this->scanned_files)) {
		
	}
		
		return <<<h
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
    <title class="title">$title</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
	
	$css
	<script src="js/jquery.js"></script>
	<script src="js/jquery.mobile-1.2.0.min.js"></script>
	<script type="text/javascript">
jQuery(function (\$) {
 //HTML
 var html = {
	list_box: function(id, title) {
		var l ='<div id="'+id+'" data-role="collapsible" data-collapsed="false" data-theme="a" data-content-theme="b">';
	    l+='<h3>'+title+'</h3>';
	    l+='<ul data-role="listview">';
	    l+='</ul>';
	    l+='</div>'; 
	    return l;
	},
	listviewItem: function(uri, name){
		return '<li><a data-ajax="false" class="debug_log" href="'+uri+'" data-transition="flip">'+name+'</a></li>';	
	}
 };
 

});
	</script>
	<style type="text/css">
	 $style
	</style>
    </head>
    <body>
	<div data-role="page" data-theme="b" id="$rest">
	    <div data-role="header"  data-position="fixed"><h1 class="title">$title</h1>
	    	<div data-role="navbar" data-iconpos="left" >
		    	<ul>
					$p
					<li><a data-rel="back"  href="#" data-icon="back" />Back</a></li>
					<li><a  href="#book_toc" data-transition="slidedown" id="toc_list" data-icon="grid"/>TOC</a></li>
					$n
		    	</ul>
			</div>
	    </div> 
	    <div data-role="content" id="html">
		    $html
	    </div>
	    $footer
	 </div> 
   <div data-role="page" data-theme="b" id="book_toc">
	    <div data-role="header" data-position="fixed">
	    	<h1 id="title">$title - TOC</h1>
	    	<div data-role="navbar" data-iconpos="left" >
		    	<ul>
					<li><a id="home" data-transition="slideup" href="$home" data-icon="arrow-l">Home</a></li>
					<li><a data-rel="back" data-transition="slideup" href="#" data-icon="back" />Back</a></li>
					<li><a id="info"  data-transition="slideup" href="#info" data-icon="arrow-r" >Info</a></li>
		    	</ul>
			</div>
		 </div> 
	   <div data-role="content" >
			<div data-theme="b" data-content-theme="c">$links</div>
	   </div>
	   $footer
	</div> 
	<div data-role="page" data-theme="b" id="info">
		<div data-role="header" data-position="fixed"><h1 id="title">$title - Info</h1>
	    	<div data-role="navbar" data-iconpos="left" >
		    	<ul>
					<li><a id="home" data-transition="slideup" href="$home" data-icon="arrow-l">Home</a></li>
					<li><a data-rel="back" data-transition="slideup" href="#" data-icon="back" />Back</a></li>
					<li><a id="info"  data-transition="slideup" href="#$rest" data-icon="arrow-r" >Resume </a></li>
		    	</ul>
			</div>
		</div> 
	  <div data-role="content" >
			<div data-theme="b" data-content-theme="c">
				<div data-role="collapsible-set">
					<div data-role="collapsible" data-collapsed="false"><h3>Title</h3><p>$title</p></div>
					<div data-role="collapsible"><h3>Creator</h3><p>$creator</p></div>
					<div data-role="collapsible"><h3>Publisher</h3><p>$publisher</p></div>
					<div data-role="collapsible"><h3>Description</h3><p>$desc</p></div>
					<div data-role="collapsible"><h3>Subject</h3><p>$subj</p></div>
					<div data-role="collapsible"><h3>Date</h3><p>$date</p></div>
					<div data-role="collapsible"><h3>Language</h3><p>$lang</p></div>
				</div>			
				$info
			</div>
			<hr/>$debug
	 </div>
	 $footer
	</div> 
 </body>
</html>		
h
;
	}
	

}
