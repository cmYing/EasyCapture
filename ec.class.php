<?PHP
class ec_page {

	//  function __construct() {
	function ec_page() {
		$this->version = "0.5";
		$this->auth_delete = 1;
		$this->auth_rename = 1;
		$this->auth_capture = 0;
		$this->debug = 1;

		umask(0133);

		$this->image_extension = array("gif"=>1,"jpg"=>1,"png"=>1,"jpeg"=>1);
		session_start();

	}

	function html($html) {
		$content = join("",file('xhtml-template.html'));

		if (!$this->valid_admin_login()) {
			$login_bar = $this->login_bar();
		}
	
		#$style = $this->default_style();
		$script = $this->script;
		if (!is_array($script)) { $script = array($script); }
		$body .= $html;

		#$body = preg_replace("/&/","&amp;",$body);

		if ($this->warning) {
			$body = "<div class=\"warning\"><b>Warning:</b> $this->warning</div>\n\n" . $body;
		}

		if ($this->information) {
			$body = "<div class=\"information\">$this->information</div>\n\n" . $body;
		}

		foreach ($script as $file) {
			$script_html .= "<script type=\"text/javascript\" src=\"$file\"></script>\n";
		}

		$content = preg_replace("/\{style\}/",$style,$content);
		$content = preg_replace("/\{script\}/",$script_html,$content);
		$content = preg_replace("/\{header\}/",$login_bar,$content);
		$content = preg_replace("/\{footer\}/",$this->footer,$content);
		$content = preg_replace("/\{body\}/",$body,$content);
		$content = preg_replace("/\{title\}/",$this->title,$content);
		$content = preg_replace("/\{link\}/",$this->link,$content);

		if ($this->valid_admin_login(0)) {
			$content = preg_replace("/\{body_opts\}/","style=\"background: #fffee8\"",$content);
		} else {
			$content = preg_replace("/\{body_opts\}/","",$content);
		}

		print $content;

		exit;
	}

	function error($html) {
		$content = join("",file('xhtml-template.html'));
	
		#$style = $this->default_style();
		if ($this->script) {
			foreach ($this->script as $file) {
				$script_html .= "<script type=\"text/javascript\" src=\"$file\"></script>\n";
			}
		}

		$body = "<h2 style=\"text-align: center; margin-top: 10em;\">$html</h2>";

		#$body = preg_replace("/&/","&amp;",$body);

		$content = preg_replace("/\{style\}/",$style,$content);
		$content = preg_replace("/\{script\}/",$script_html,$content);
		$content = preg_replace("/\{body\}/",$body,$content);
		$content = preg_replace("/\{title\}/",$this->title,$content);
		$content = preg_replace("/\{link\}/",$this->link,$content);

		$content = preg_replace("/{\w+}/","",$content);

		print $content;

		exit;
	}

	 function get_files($offset = 0, $limit = 30, $filter = "") {
		$offset = intval($offset);

		if ($this->debug) { print "get_files: offset=$offset limit=$limit filter=$filter<br />\n"; }

		$full_html_dir = $this->full_dir;
		$thumb_html_dir = $this->thumb_dir;

		// print "$full_html_dir - $thumb_html_dir $this->full_dir";
	
		$images = glob($this->full_dir . "/*");

		// If we're getting binaries too, then get those as well
		if ($this->enable_binary_capture) {
			$binaries = glob($this->binary_dir . "/*");
		} else { 
			// Without an array, array merge fails
			$binaries = array();
		}

		// These two foreach statements makes sure we're not adding the . and .. dirs
		foreach ($binaries as &$file) {
			if (is_readable($file) && !is_dir($file)) {
				$list[] = $file;
			}
		}

		foreach ($images as &$file) {
			if (is_readable($file) && !is_dir($file)) {
				$list[] = $file;
			}
		}

		#$list = array_merge($images,$binaries);
		//print_r($list);

		// This just gets ALL the files and sorts them according to the times
		foreach ($list as $file) {

			// If there is a filter and it matches it
			if ($filter && preg_match("/$filter/",$file)) {
			} elseif ($filter) { 
				// there is a filter and this file doesn't match it
				//print "Skipping $file it doesn't match the filter<br />\n";
				continue;
			}
		
			$stat = stat($file);
			$mtime = $stat[9];
			$file = basename($file);

			$for_sorting["$mtime-$file"] = $file;
		}

		if (!$for_sorting) { $this->error("No files found to show!"); }

		// Sort the newly created array
		krsort($for_sorting);
		$sorted = $for_sorting;

		// Filter out what's not needed after the filter and the limits etc...
		foreach ($sorted as $filename) {
			$count++;
		
			$filename = basename($filename);
		
			if ($count >= $offset && $shown_count < $limit) {
				//print "$filename<br />\n";
				$info = $this->get_file_info($filename);
				$ret[$filename] = $info;
				$shown_count++;
			}
		}

		// print_r($ret);

		return $ret;
	}

	 function show_gallery() {
		$full_html_dir = $this->full_dir;
		$thumb_html_dir = $this->thumb_dir;
		$start = microtime(1);

		$offset = intval($_GET['offset']);
		$limit = $_GET['limit'];
		$filter = $_GET['filter'];

		$limit || $limit = 30;

		if ($_GET['delete']) { 
			$this->delete_file($_GET['delete']); 
		} elseif ($_GET['old_name'] && $_GET['new_name']) {
			$this->rename_file($_GET['old_name'],$_GET['new_name']);
		}

		$show_stats = 1;
		if ($show_stats) {
			$info = glob($this->full_dir . "/*");

			// Calc the size for each file (in bytes)
			foreach ($info as $file) {
				$size += filesize($file);
			}

			// Convert bytes to megs
			$size = $size / (1024 * 1024);
			$size = sprintf("%.02f",$size);
			$total = sizeof($info); // number of files in the dir

			$stats = "$total files - {$size} megs - ";
		}

		$version = $this->version;
		$out .= "<h2 class=\"gallery_header\">Easy Capture Version $version</h2>\n\n";
		$out .= "<div class=\"back_to_menu\">$stats(<a href=\"index.php\">Back to Menu</a>)</div>\n\n";

		$files_to_show = $this->get_files($offset,$limit,$filter);
		if (!$files_to_show) { $this->error("No files to show"); }

		// This just gets a hash with the name=>mtime
	
		#print_r($full);
		$PHP_SELF = $_SERVER['PHP_SELF'];

		$out .= "<form method=\"get\" action=\"$PHP_SELF\" style=\"text-align: center; border: 0px solid; margin-bottom: 10px; display: none;\" id=\"form\">
		<input type=\"text\" size=\"30\" name=\"old_name_text\" value=\"old_name.jpg\" id=\"old_name_text\" disabled=\"disabled\" />
		<input type=\"hidden\" size=\"30\" name=\"old_name\" value=\"old_name.jpg\" id=\"old_name\" />
		-&gt;	
		<input type=\"text\" size=\"30\" name=\"new_name\" value=\"new_name.jpg\" id=\"new_name\" />
		<input type=\"hidden\" size=\"30\" name=\"show\" value=\"gallery\" />
		<input type=\"submit\" id=\"rename_button\" value=\"Rename\" onclick=\"javascript: return final_submit();\" />
		<span id=\"placeholder\"></span>
	</form>\n\n";

		#print join("<br />",$full) . "<br />;

		// This outputs what's in the $full array
		foreach(array_keys($files_to_show) as $filename) { 
			#print "Checking $filename<br />";

			$footer = "\t<a href=\"$PHP_SELF?show=$filename\"><b>$filename</b></a><br />\n";

			if ($this->authorized("delete")) {
				$footer .= $this->get_delete_link($filename);
			}
			if ($this->authorized("rename")) {
				$footer .= "\n\t| <a href=\"$PHP_SELF?show=gallery\" onclick=\"javascript: return rename_file('$filename',''); \">Rename</a>";
			}

			//print_r($files_to_show);
			//print "$filename<br />";

			// Is a binary
			if ($files_to_show[$filename]['binary_size']) {
				$path = $this->clean_url($files_to_show[$filename]['thumb_html_path']);
				$link_path = $this->clean_url($this->binary_dir . "/" . $filename);

				$PHP_SELF = $_SERVER['PHP_SELF'];
				$file_size = number_format($files_to_show[$filename]['binary_size']);
	
				$footer = "\t<a href=\"$PHP_SELF?show=$u_filename\"><b>$filename</b></a><br />\n<div class=\"file_size\">$file_size bytes</div>\n";
				if ($this->authorized("delete")) {
					$footer .= $this->get_delete_link($filename);
				}
				if ($this->authorized("rename")) {
					$footer .= "\n\t| <a href=\"$PHP_SELF?show=gallery\" onclick=\"javascript: return rename_file('$filename',''); \">Rename</a>";
				}
				
				$out .= "<div class=\"image\">\n\t<a href=\"$link_path\"><img src=\"binary-icon.png\" style=\"border: 0px solid green;\" alt=\"$filename\" /></a>\n\t<br />\n$footer\n</div>\n\n";

			// Has thumbnail
			} elseif ($files_to_show[$filename]['thumb_html_path']) {
	
				//$path = $this->clean_url($thumb_html_dir . "/" . $filename);
				$path = $this->clean_url($files_to_show[$filename]['thumb_html_path']);
				$link_path = $this->clean_url($full_html_dir . "/" . $filename);
	
				$PHP_SELF = $_SERVER['PHP_SELF'];
	
				#$footer .= "\n\t| <a href=\"$PHP_SELF?show=gallery\" onclick=\"javascript: return rename_file('$filename',''); \">Rename</a>";
				
				$out .= "<div class=\"image\">\n\t<a href=\"$link_path\"><img src=\"$path\" style=\"border: 1px solid green;\" alt=\"$filename\" /></a>\n\t<br />\n$footer\n</div>\n\n";
			// No thumbnail
			} else {
				$path = $full_html_dir . "/" . $filename;
				$out .= "<div class=\"image\">\n\t<a href=\"$path\"><img src=\"$path\" style=\"border: 0px solid;\" alt=\"$filename\" /></a>\n<br />\n$footer\n</div>\n\n";
	
			}

			$shown_images++;
		}
		//print sprintf("%.3f seconds<br />",microtime(1) - $start);

		// Only show the more if the page is full (i.e. there are more images)
		if ($shown_images >= $limit) {
			$new_offset = $offset + $limit;
			$older_link = "$PHP_SELF?show=gallery&amp;offset=$new_offset";
			$older_html .= "<a href=\"$older_link\">Older</a>\n";
		} else {
			$older_html .= "Older\n";
		}

		// Setup the new offset link
		if ($offset > 0) {
			$new_offset = $offset - $limit;
			if ($new_offset < 0) { $new_offset = 0; }
			$newer_html = "<a href=\"$PHP_SELF?show=gallery&amp;offset=$new_offset\">Newer</a>";
		} else {
			$newer_html = "Newer";
		}

		$PHP_SELF = $_SERVER['PHP_SELF'];

		$filter_html = "<b>Filter:</b> <form method=\"get\" action=\"$PHP_SELF\" style=\"display: inline;\"><input type=\"input\" class=\"footer_input\" name=\"filter\" /><input type=\"hidden\" name=\"show\" value=\"gallery\" /></form>";

		$this->footer .= "<div class=\"gallery_footer\">
			<div class=\"footer_right\">$newer_html</div>
			<div class=\"footer_filter\">$filter_html</div>
			<div class=\"footer_left\">$older_html</div>
			<div class=\"clear\"></div>
		</div>\n";
	
		return $out;
	}

	 function get_file_info($filename) {

		if (is_array($filename)) { 
			if ($this->debug) { print "Image info for an Array!?! Return 0<br />\n\n"; }
			return 0; 
		}

		if ($this->debug) { print "get_file_info for <b>$filename</b><br />\n"; }

		$ret['filename'] = $filename;

		if ($this->is_binary_file($filename)) {
			$ret['binary_html_path'] = $this->clean_url($this->binary_dir . "/$filename");
			$stat = stat($ret['binary_html_path']);
			$ret['mtime'] = $stat[10];
			$ret['binary_size'] = filesize($ret['binary_html_path']);

			return $ret;
		}

		if ($this->debug > 1) { print "Image info for <b>$filename</b><br />\n\n"; }

		$full_html_dir = $this->full_dir;
		$thumb_html_dir = $this->thumb_dir;
		
		if (is_readable($this->full_dir . "/$filename")) {
			#print "Is readable";
		} else {
			#print "$filename not found";
			//exit;
			return 0;
		}

		$ret['full_html_path'] = $this->clean_url("$full_html_dir/$filename");
		$ret['full_file_size'] = @filesize($ret['full_html_path']);

		if (is_readable($this->clean_url("$thumb_html_dir/$filename"))) {
			$ret['thumb_html_path'] = $this->clean_url("$thumb_html_dir/$filename");
			$ret['thumb_file_size'] = filesize($ret['thumb_html_path']);
		} else {
			$thumb_filename = $this->check_thumb_other($filename);

			if ($thumb_filename) {
				$ret['thumb_html_path'] = $this->clean_url("$thumb_html_dir/$thumb_filename");
				$ret['thumb_file_size'] = filesize($ret['thumb_html_path']);
				if ($this->debug) { print "<b>$filename</b> has alternate thumbnail extension <b>$thumb_filename</b><br />\n"; }
			} else {
				if ($this->debug) { print "<b>$filename</b> has no thumbnail<br />\n"; }
			}
		}

		$stat = stat($ret['full_html_path']);
		$ret['mtime'] = $stat[9];

		return $ret;
	}

	// Used if the thumb and fullsize don't have the same extension (thumbs are always .jpg)
	function check_thumb_other($filename) {
		$wo_ext = preg_replace("/^(.+)\.(.+)/","$1",$filename); 
		// $wo_ext = substr($filename,0,strlen($filename) - 4);

		if (!$wo_ext) { $this->error("Filename has no extension!?! '$filename'"); }

		foreach (array_keys($this->image_extension) as $ext) {
			$path = $this->thumb_dir . $wo_ext . ".$ext";

			if (is_readable($path)) { 
				return basename($path);
			}
		}

		// If you get this far, it didn't find anything
		return 0;
	}

	function clean_url($url,$remove_periods = 0) {
		if (!$url) { return ""; }
		$split = preg_split("|/|",$url);

		if ($this->debug > 1) { print "URL Before: $url<br />\n"; }

		// If there are a bunch of ../../ or ./ in the url, remove them
		if ($remove_periods)	{
			foreach ($split as $item) {
				// Add the new item to the list since it's not a ..
				if ($item != ".." && $item != "." && $item != "") {
					$ret[] = $item;
				// If it's a ./ then it's nothing (just that dir) so don't add/delete anything
				} elseif ($item == "." || $item == "") { 
				} elseif ($item == ".." && is_array($ret)) {
					// Remove the last item added since .. negates it.
					array_pop($ret);
				}
			}

			// Rebuild the string
			$ret = join("/",$ret);

			if (substr($url,0,1) == "/") { $ret = "/" . $ret; }
		} else {
			$ret = $url;
		}
	
		// Remove dupe /
		$ret = preg_replace("!/+!","/",$ret);
	
		// Restore the double / after the HTTP:
		$ret = preg_replace("!(https?:/)!","$1/",$ret);

		if ($this->debug > 1) { print "URL After: $ret<br />\n"; }
	
		return $ret;
	}

	 function show_image($file_list,$show_all_link = 1) {
		//print_r($file_list);

		if (!$file_list) { $this->error("No files sent to show_image"); }

		if (!is_array($file_list)) {
			$filename = $file_list;
			$file_list = "";
			$file_list[] = $filename;
		}

		// print_r($file_list);

		foreach ($file_list as $filename) {
			$img_list[] = $this->get_file_info($filename);
		}

		if ($show_all_link) { 
			$head .= "<h2 style=\"text-align: center;\"><a href=\"$PHP_SELF?show=gallery\">Show all gallery images</a></h2>\n\n"; 
		}

		$head .= "<div style=\"text-align: center;\">\n\n";
	
		foreach ($img_list as $img_info) {
			// Is a directory
			if (substr($_SERVER['REQUEST_URI'],-1,1) == "/" ) { $directory = $_SERVER['REQUEST_URI']; }
			// Is a file
			else  { $directory = dirname($_SERVER['REQUEST_URI']) . "/" ; }

			$server_name = "http://" . $_SERVER['SERVER_NAME'] . $directory;
			//$server_name = "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

			$html_path = $server_name . $img_info['full_html_path'];
			$filename = basename($html_path);

			$thumb_html_path = $img_info['thumb_html_path'];
			$filename = $img_info['filename'];
			$filesize = number_format($img_info['binary_size']);
			$u_filename = urlencode($img_info['filename']);
	
			// Binary file
			if ($img_info['binary_size']) {
				$path = dirname($img_info['binary_html_path']);
				$filename = basename($img_info['binary_html_path']);
				$file_link = $this->clean_url($server_name . $path . "/" . urlencode($filename),1);
			
				$img_html .= "<div style=\"margin-bottom: 10px;\">\n\t<a href=\"$file_link\"><img src=\"binary-icon.png\" style=\"border: 0px solid;\" alt=\"$filename\" /></a>\n</div>\n<div class=\"file_size\">$filename - $filesize bytes</div>\n\n";
			// No thumbnail
			} elseif (!$thumb_html_path) { 
				$thumb_html_path = $server_name . "/" . $img_info['full_html_path'];
				$thumb_html_path = $this->clean_url($thumb_html_path,1);
			
				$img_html .= "<div style=\"margin-bottom: 10px;\">\n\t<img src=\"$thumb_html_path\" style=\"border: 0px solid;\" alt=\"$filename\" />\n</div>\n\n";
			// It has a thumbnail
			} else {
				$thumb_html_path = $server_name . $thumb_html_path;
				$thumb_html_path = $this->clean_url($thumb_html_path,1);
				$html_path = $this->clean_url($html_path,1);

				$img_html .= "<div style=\"margin-bottom: 0px;\">\n\t<a href=\"$html_path\"><img src=\"$thumb_html_path\" style=\"border: 1px solid;\" alt=\"$filename\" /></a>\n</div>\n\n";
			}

			//print "<div><img src=\"$html_path\"><br />$filename</div>\n";
			if ($this->freetype && $img_info['thumb_html_path'] ) {
				$PHP_SELF = $_SERVER['PHP_SELF'];
				$remove_link = "$PHP_SELF?action=remove_tag&filename=$filename";
				$add_link = "$PHP_SELF?action=add_tag&filename=$filename";

				if ($this->authorized('delete')) { 
					$del_link = $this->get_delete_link($filename,"Delete this file");
				}

				if ($this->valid_admin_login()) { 
					$raw_html .= "\n\t<div style=\"margin-bottom: 10px; font-size: 0.7em;\">Info Tag: <a href=\"$add_link\">On</a>/<a href=\"$remove_link\">Off</a></div>$del_link\n\n";
					$raw_html .= "<a href=\"$PHP_SELF?action=resample&filename=$filename\">Resample</a>";
				} else {
					$raw_html = "<br />";
				}
			}
		}
			
		$text_area_content = preg_replace("/[\t\n\r]+/","",$img_html);
		$text_area_content = htmlspecialchars($text_area_content);

		$raw_html .= "<form action=\"post\"><textarea rows=\"5\" cols=\"100\">" . $text_area_content . "</textarea></form>\n";

		// Close the div that centers everything
		$raw_html .= "</div>";

		$PHP_SELF = $_SERVER['PHP_SELF'];

		$out = $head . $img_html . $raw_html;
	
		return $out;
	}	

	function capture_image($url) {
		if (!$url) { return 0; }
		if ($this->debug) { print "You want to capture: <b>$url</b><br />\n"; }

		if (!$this->authorized('capture')) { 
			$PHP_SELF = $_SERVER['PHP_SELF'];
			$this->error("You are not allowed to capture images<br />Please <a href=\"$PHP_SELF?login=1\">login</a> first"); 
		}
	
		$full_dir = $this->full_dir;
		$thumb_dir = $this->thumb_dir;
	
		$full_html_dir = $full_dir;
		$thumb_html_dir = $thumb_dir;
	
		#$filename = str_replace("%20","_",basename($url));
		$filename = urldecode(basename($url));

		// If it's an image name that already exists ask for overwrite
		$info = $this->get_file_info($filename);
		$confirm = $_POST['confirm'];
		$action = $_GET['action'];

		// If it's an action (add/remove info tag) auto confirm
		if ($action) { $confirm = 1; }

		// If the file already exists, and they haven't confirmed
		if ($info['full_html_path'] && !$confirm) {
			$this->confirm_overwrite($url);
		}
	
		if ($this->debug) { print "Filename: <b>$filename</b><br />\n"; }
		$filename = preg_replace("/\s/","_",$filename);
		$thumb_filename = $this->file_without_ext($filename) . ".jpg";

		$extension = $this->get_extension($filename);

		// Binary capture is OFF and it's not an image
		if (!$this->image_extension[$extension] && !$this->enable_binary_capture) { 
			$this->error("Unknown extension '$extension'"); 
		// Binary capture is on and it's not an image
		} elseif (!$this->image_extension[$extension] && $this->enable_binary_capture) { 
			$bytes = $this->binary_capture($url);
			if ($bytes <= 0) {
				$this->error("Something went wrong trying to capture <b>$filename</b>");
			} else {
				$ret = $this->get_file_info($filename);
			}

			return $ret;
		}

		$filename = strtolower($filename);
		$thumb_filename = strtolower($thumb_filename);

		if ($this->debug) { print "Filename for the thumbnail: <b>$thumb_filename</b><br />\n"; }
	
		$file_contents = @join("",file($url));
		if (!$file_contents) { $this->error("Could not download that file '$url'"); }

		$filesize = (strlen($file_contents) / 1024);
		$filesize = sprintf("%.1f",$filesize) . "k";
		
		$img = @imagecreatefromstring($file_contents);
		if (!$img) { $this->error("Something went wrong trying to capture <b>$filename</b>"); }
		
		$time = time();
		
		$full_path = $this->clean_url("$full_dir/$filename");
		$full_thumb_path = "$thumb_dir/$thumb_filename";
		
		if ($this->debug) { print "Writing file to disk: <b>$full_path</b><br />\n"; }
		
		$fp = fopen($full_path,"w");
		$bytes = intval(fwrite($fp,$file_contents));
		fclose($fp);

		if (!$bytes) {
			$this->error("Tried writing the image to disk but it didn't work ($bytes written)");
		}
	
		$file_contents = "";
		
		if ($this->debug) { print "Creating thumbnail for: <b>$full_path</b> size: $filesize<br />\n"; }

		// If they don't have freetype installed then just don't add the info
		if ($this->freetype == 0) {
			$filesize = 0;
			$this->warning = "You don't have the FreeType library compiled in, some functionality will be limited.";
		// Or if the specify no image info (don't add the footer to the image)
		} elseif (!$this->include_info_tag) {
			$filesize = 0;
		}

		$thumb = $this->create_thumbnail($img,$filesize);

		if ($thumb) {
			imagejpeg($thumb,$full_thumb_path,$this->jpeg_quality);
			
			//$thumb_filesize = filesize($full_thumb_path);
			//$ret['thumb_html_path'] = preg_replace("|//|","/","$thumb_html_dir/$thumb_filename");
			//$ret['thumb_disk_path'] = $full_thumb_path;
		}
		
		//list($width, $height, $type, $attr) = getimagesize($full_path);
		//print "That image is $width x $height pixels<br />";

		$ret = $this->get_file_info($filename);
	
		return $ret;
	}
	
	 function delete_file($filename) {
		if (!$this->authorized('delete')) { 
			$PHP_SELF = $_SERVER['PHP_SELF'];
			$this->error("You are not allowed to delete images<br />Please <a href=\"$PHP_SELF?login=1\">login</a> first"); 
		}

		if (!$filename) { $this->error("No file to delete"); }
	
		// If there is a thumb we need to figure out where it is so we can delete it
		$info = $this->get_file_info($filename);
		$thumb_file = $info['thumb_html_path'];

		if ($this->is_binary_file($filename)) {
			if (@unlink($this->binary_dir . "/$filename")) {
				$out .= "Info: Deleted binary file <b>$filename</b><br />\n";
			}
		} else {
			// print_r($info);

			if (@unlink($this->full_dir . "/$filename")) {
				$out .= "Info: Deleted fullsize <b>$filename</b><br />\n";
			}

			if ($thumb_file) {
				unlink($thumb_file);
				$out .= "Info: Deleted thumbnail <b>$filename</b><br />\n";
			}
		}

		$this->information = $out;

		return 1;
	}

	function rename_file($old_name,$new_name) {
		if (!$this->authorized('rename')) { 
			$PHP_SELF = $_SERVER['PHP_SELF'];
			$this->error("You are not allowed to rename images<br />Please <a href=\"$PHP_SELF?login=1\">login</a> first"); 
		}

		// You reloaded the rename page, and thus the old name isn't there
		// if (!is_readable($this->full_dir . "/$old_name")) {
		if (!$this->get_file_info($old_name)) {
			//$this->error("adsfasd");
			return 0;
		}

		$file_info = $this->get_file_info($old_name);
		if (!$file_info) { return 0; }

		// Make sure it's an allowed extension and not .txt or something weird
		$ext = $this->get_extension($new_name);
		// if (!$this->image_extension[$ext]) { $this->error("Can't rename a file unless the extension is valid ('$ext')"); }
	
		// Prevent someone from renaming a file to /etc/foo.cfg or something
		$old_name = basename($old_name);
		$new_name = basename($new_name);

		// Make sure they're not trying to rename from .jpg to .png
		if ($this->get_extension($old_name) != $this->get_extension($new_name)) {
			$this->error("Sorry the extensions don't match on your rename, they must stay the same!");
		}
	
		// Don't overwrite a file that's already there
		if (is_readable($this->full_dir . "/$new_name")) {
			$this->error("Can't rename to $new_name, it's already used");
		}

		// It's a binary file
		if ($this->is_binary_file($old_name)) {
			rename($this->binary_dir . "/$old_name",$this->binary_dir . "/$new_name");
		// It's an image
		} else {
			//print_r($img_info);
	
			// Rename the full size
			rename($this->full_dir . "/$old_name",$this->full_dir . "/$new_name");
	
			// Only rename the thumb if it has one
			if ($file_info['thumb_html_path']) {
				$thumb_name = basename($file_info['thumb_html_path']);
				$new_thumb = $this->file_without_ext($new_name) . "." . $this->get_extension($thumb_name);
	
				//print "$thumb_name $new_thumb $old_name";
	
				rename($this->thumb_dir . "/$thumb_name",$this->thumb_dir . "/$new_thumb");
			}
		}

		return 1;
	}

	function sanity_check() {
		if ($this->debug) { $this->image_types(); }
		if ($this->debug) { $this->show_php_settings(); }
	
		if (!is_dir($this->thumb_dir)) { 
			$this->error("Thumbnail directory does not exist<br />$this->thumb_dir");
		}

		if (!is_dir($this->full_dir)) { 
			$this->error("Full size image directory does not exist<br />$this->full_dir");
		}

		if (!is_writeable($this->thumb_dir)) { 
			$this->error("Thumbnail directory in not writeable<br />$this->thumb_dir");
		}

		if (!is_writeable($this->full_dir)) {
			$this->error("Full size directory is not writeable<br />$this->full_dir");
		}

		if ($this->enable_binary_capture && !is_writeable($this->binary_dir)) {
			$this->error("Binary directory is not writeable<br />$this->binary_dir");
		}

		#if (!function_exists('imagecreatefromjpeg')) {
		if (!imagetypes() & IMG_JPG) {
			$this->error("Easy Capture requires JPEG support, please enable JPEG support and try again. (--with-jpeg-dir)");
		}

		if (!function_exists("imagecopyresampled")) {
			$this->error("Easy Captures requires the GD Library but it's not present in this PHP install.<br />Please recompile PHP with GD support (--with-gd)");
		}

		if (!function_exists("imagettftext")) {
			#$this->error("Easy Captures requires the Freetype support but it's not present in this PHP install.<br />Please recompile PHP with Freetype enabled (--with-freetype)");
			$this->freetype = 0;
		} else {
			$this->freetype = 1;
		}

		if ($this->thumb_dir == $this->full_dir) {
			$this->error("Thumb dir and Full dir cannot be the same.<br />Change them in index.php and retry!");
		}
	}

	function authorized($action) {
		#$valid_login = $this->valid_admin_login(0);

		// Check if auth is required, if it is check for a valid login
		if ($this->auth_delete && ($action == "delete" && !$this->valid_admin_login(1))) { return 0; }
		elseif ($this->auth_rename && ($action == "rename" && !$this->valid_admin_login(1))) { return 0; }
		elseif ($this->auth_capture && ($action == "capture" && !$this->valid_admin_login(1))) { return 0; }
		else { return 1; }
	}

	function get_extension($filename) {
		//print "!$filename!";
		if (!$filename) { return 0; }
	
		preg_match("/.*\.(\w{1,5})$/",$filename,$match);
		$ret = $match[1];

		$ret = strtolower($ret);

		return $ret;
	}

	function file_without_ext($filename) {
		//print "!$filename!";
		if (!$filename) { return 0; }
	
		preg_match("/(.*)\./",$filename,$match);
		$ret = $match[1];

		return $ret;
	}

	function get_tar_list($filename,$images_only = 0) {
		if (!is_readable($filename)) {
			die("File not found '$filename'");
		} 

		if (preg_match("/.tar.gz$/",$filename)) {
			$tar_opt = "-tvzf";
		} elseif (preg_match("/.tar.bz2$/",$filename)) {
			$tar_opt = "-tvjf";
		} else {
			$this->error("Not a tar file <b>$filename</b>");
		}
	
		$cmd = "tar $tar_opt $filename";
		$foo = preg_split("/\n/",exec($cmd,$out));
	
		foreach ($out as $line) {
			//print "$line<br />\n";
			preg_match("/\d{2}:\d{2}:\d{2} (.+)/",$line,$match);
			$filename = $match[1];
			
			// If they only want to see the images, only return those
			if ($images_only) {
				$ext = $this->get_extension($filename);

				// If the extension if one of the ones listed in images, return it
				if ($this->image_extension[$ext]) {
					$ret[] = $filename;
				} 
			} else {
				$ret[] = $filename;
			}
		}

		return $ret;
	}
	
	function extract_tar_file($filename,$out_dir = "/tmp/",$file_list = "") {
		//$out_dir .= "/easy_capture";
		if (!is_readable($filename) || !is_writable($out_dir)) { return 0; }

		if (preg_match("/.tar.gz$/",$filename)) {
			$tar_opt = "-xvzf";
		} elseif (preg_match("/.tar.bz2$/",$filename)) {
			$tar_opt = "-xvjf";
		} else {
			$this->error("Not a tar file <b>$filename</b>");
		}
	
		if ($file_list) {
			foreach ($file_list as $item) {
				$file_list2[] = "\"" . trim($item) ."\"";
			}
			$files_to_extract = join(" ",$file_list2);
		} else {
			$files_to_extract = "";
		}

		$cmd = "tar -C $out_dir $tar_opt $filename $files_to_extract";
		if ($this->debug) { print "tar command: $cmd<br />\n"; }
		$foo = preg_split("/\n/",exec($cmd,$out));
	
		foreach ($out as $line) {
			$ret[] = $this->clean_url($out_dir . trim($line),1);
		}

		return $ret;
	}

	function remove_tag($filename) {
		if (!$this->valid_admin_login(1)) {
			$this->error("You must be logged in to change images");
		}

		if (!$filename) { return 0; }
		$info = $this->get_file_info($filename);

		if (!$info) { return 0; }

		$url = $info['full_html_path'];

		$this->include_info_tag = 0;
		$this->capture_image($url);

		return 1;
	}

	function add_tag($filename) {
		if (!$this->valid_admin_login(1)) {
			$this->error("You must be logged in to change images");
		}

		if (!$filename) { return 0; }
		$info = $this->get_file_info($filename);
		
		if (!$info) { return 0; }

		$url = $info['full_html_path'];

		$this->include_info_tag = 1;
		$this->capture_image($url);

		return 1;
	}

	function create_thumbnail(&$img,$file_size = 0) {
		$width = imagesx($img);
		$height = imagesy($img);
		$ratio = $width / $height;
	
		$min_size = 200;
		//$file_size = 0;
	
		if ($width <= $min_size && $height <= $min_size) { 
			#print "No making thumbnail, too small";
			return 0; 
		}
	
		if ($width > $height) {
			$new_w = $min_size;
			$new_h = intval($new_w / $ratio);
		} else {
			$new_h = $min_size;
			$new_w = intval($new_h * $ratio);
		}
	
		$text_height = 15;
	
		// Don't add the info text at the bottom of the thumb
		if ($file_size == 0) { 
			if ($this->debug) { print "Not adding info tag for thumbnail<br />\n"; }
			$text_height = 0; 
			$new_img = imagecreatetruecolor($new_w, $new_h);
			imagecopyresampled($new_img,$img,0,0,0,0,$new_w,$new_h,$width,$height);
		} else {
			$new_img = imagecreatetruecolor($new_w, $new_h + $text_height);
			imagecopyresampled($new_img,$img,0,0,0,0,$new_w,$new_h,$width,$height);
	
			$black = ImageColorAllocate($new_img,0,0,0);
			$white = ImageColorAllocate($new_img,255,255,255);
			
			#imagefilledrectangle($new_img,0,$new_h-$text_height,$new_w,$new_h,$black);
			imagefilledrectangle($new_img,0,$new_h,$new_w,$new_h + $text_height,$black);
		
			$font_file = "./emblem.ttf";
			$font_size = 9;
			$padding = 3;

			// Images is too narrow for an info tag
			if ($new_w <= 40) {
				$image_text = "";
			// Images is too narrow for a full info tag, thus limited tag
			} elseif ($new_w <= 100) {
				$image_text = $file_size;
			// Full info tag
			} else {
				$image_text = "{$width}x{$height}  -  $file_size";
			}
		
			if ($this->debug) { print "Thumbnail info tag: $image_text<br />\n"; }
			$array = imagettfbbox($font_size,0,$font_file,$image_text);
		
			#print_r($array);
		
			$text_width = $array[4] - $array[0];
			$left_offset = ($new_w - $text_width) / 2;
		
			#print "$new_w ; $text_width ; {$array[6]} ; {$array[0]} ; $left_offset";
		
			imagettftext($new_img,$font_size,0,$left_offset,($new_h + $text_height) - $padding,$white,$font_file,$image_text);
		}
	
		return $new_img;
	}

	function confirm_overwrite($url) {
		$filename = basename($url);
	
		$html .= "<h1 class=\"large_header\">Image $filename already in use.</h1>\n";
		$html .= "<h2 class=\"medium_header\">Overwrite $filename?</h2>\n";
		
		$html .= "<form method=\"post\" style=\"text-align: center;\">
			<input type=\"hidden\" value=\"$url\" name=\"url\">
			<input type=\"submit\" value=\"Yes\" name=\"confirm\">
			<input type=\"button\" value=\"No\" onclick=\"javascript: location.href = 'index.php';\">
		</form>";
		$this->html($html);
	}

	function image_types() {
		$types = array(IMG_GIF,IMG_JPG,IMG_PNG,IMG_WBMP,IMG_XPM);
		foreach ($types as $type) {
			if (imagetypes() & $type) {
				//print "Image type: $type is supported<br />\n";
			}
		}
		if (imagetypes() & IMG_GIF) { $supported[] = "GIF"; }
		if (imagetypes() & IMG_JPG) { $supported[] = "JPG"; }
		if (imagetypes() & IMG_PNG) { $supported[] = "PNG"; }
		if (imagetypes() & IMG_WBMP) { $supported[] = "WBMP"; }
		if (imagetypes() & IMG_XPM) { $supported[] = "XPM"; }

		print "Supported image types: " . join(", ",$supported) . "<br />\n";

		return $supported;
	}

	function process_files() {
		$count = sizeof(array_keys($_FILES['file']['name']) );
		#print $count;

		// Loop through the info for each file
		for ($i=0;$i < $count;$i++) {
			$old_name = $_FILES['file']['tmp_name'][$i];
			$tmp_dir = dirname($old_name);
			//$size = $_FILES['file']['size'][$i];
	
			$new_name = $tmp_dir . "/" . $_FILES['file']['name'][$i];
	
			// Rename (move) each file into each directory
			if ($_FILES['file']['tmp_name'][$i] && $_FILES['file']['name'][$i]) {
				if ($_FILES['file']['size'][$i] <= 0) {
					print "Skipping: $new_name because it's zero bytes<br />";
					continue;
				}

				if (is_file($new_name) && !is_writable($new_name)) {
					$this->error("Cannot write $new_name");
				}
				rename($old_name,$new_name);
				// chmod($new_name,0777);
	
				$ret[] = $new_name;
			}
		}

		return $ret;
	}

	// Pull the images from a list
	function images_from_list($list) { }

	function check_files() {
		$full = $this->full_dir;
		$thumb = $this->thumb_dir;

		$out .= "<div class=\"large_header\">Checking file integrity</div><br />\n\n";

		$error_count = 0;

		// Check for fulls that aren't images
		// Check for thumbs that aren't images
		$full = glob("$full/*");
		$thumb = glob("$thumb/*");

		$check = array_merge($full,$thumb);
		foreach ($check as $filename) {
			$filename = $this->clean_url($filename);
			$ext = $this->get_extension($filename);

			if (!$this->image_extension[$ext] && !is_dir($filename)) {
				$out .= "Error: <b>$filename</b> is not an image<br />\n";
				$error_count++;
			}
		}

		// Check for thumbs with no matching full
		foreach ($thumb as $filename) {
			//print "$filename<br />\n";
			$info = $this->thumb_to_fullsize(basename($filename));

			if (!$info) {
				$filename_out = $this->clean_url($filename);
				$out .= "Error: <b>$filename_out</b> no full size<br />\n";
				$error_count++;
			} 
		}

		if ($error_count ==0) {
			$out .= "<div style=\"text-align: center;\">No errors found!</div>";
		}
	
		//print_r($full);

		return $out;
	}

	function thumb_to_fullsize($path_to_thumb) {
		if (!$path_to_thumb) { return 0; }
		$full_dir = $this->full_dir;		

		$wo_ext = $this->file_without_ext($path_to_thumb);
		if (!$wo_ext) { return 0; }

		$glob_text = $full_dir . "/$wo_ext.*";
		$glob = glob($glob_text);
		$file_count = sizeof($glob);

		//print "$wo_ext ($glob_text)-> $file_count<br />\n";

		if ($file_count == 1) { 
			$ret = $glob[0];
		} else {
			$ret == $file_count;
		}

		return $ret;
	}

	function binary_capture($url) {
		$file_contents = join("",file($url));


		$filename = basename($url);
		$full_path = $this->clean_url($this->binary_dir . "/" . $filename);

		if ($this->debug) { print "Writing binary file to disk: <b>$full_path</b><br />\n"; }

		$fp = fopen($full_path,"w");
		$bytes = intval(fwrite($fp,$file_contents));
		fclose($fp);
		
		if ($this->debug) { print "Wrote $bytes bytes to disk<br />\n"; }

		return $bytes;
	}

	function is_binary_file($filename) {
		$ext = $this->get_extension($filename);

		if (is_file($this->binary_dir . "/$filename") && !$this->image_extension[$ext]) {
			return 1;
		} else {
			return 0;
		}
	}

	function show_php_settings() {
		print "PHP Setting <b>upload_max_filesize</b>: " . ini_get("upload_max_filesize") . "<br />\n";
		print "PHP Setting <b>post_max_size</b>: " . ini_get("post_max_size") . "<br />\n";
	}
	
	function show_admin_login() {
		$PHP_SELF = $_SERVER['PHP_SELF'];

		$out .= "<span class=\"header\">Please login to view the admin panel!</span>\n\n";
		$out .= "<form method=\"post\" action=\"$PHP_SELF\">\n";
		$out .= "\t<input type=\"text\" name=\"username\" value=\"username\" /><br />\n";
		$out .= "\t<input type=\"password\" name=\"password\" value=\"password\" /><br />\n";
		$out .= "\t<input type=\"submit\" value=\"Login\" style=\"margin-top: 10px; \" />\n";
		$out .= "</form>\n";

		$this->html($out);
		
		exit;
	}

	function valid_admin_login($error_out = 1) {
		$un = $_SESSION['username'];
		$pwd = $_SESSION['password'];

		static $pert;

		// This is perturb.org specific code
		if (is_readable("../../perturb.class.php")) {
			require_once("../../perturb.class.php");

			if (!$pert) {
				$pert = new page;
			}
			$user_id = $pert->user_id;

			// If they're logged in from perturb.org they're ok, otherwise make them login here too
			if ($user_id) { return $user_id; }	
		}
	
		if ($error_out && (!$this->admin_username || !$this->admin_password)) {
			$msg = "Admin Username/Password not set logins are disabled until this is corrected";
			$this->error($msg);
		}	

		//print "$un = $this->admin_username<br />\n$pwd = $this->admin_password";
		
		if (!$un && !$pwd) {
			$ret = 0;
		} elseif ($un == $this->admin_username && $pwd == $this->admin_password) {
			$ret = 1;
		} elseif ($un != $this->admin_username || $pwd != $this->admin_password) {
			$ret = 0;
		} else {
			$ret = 0;
		}

		#print "Admin login returning $ret<br />\n";
		return $ret;
	}

	function login_bar() {
		if ($_POST['username'] || $_POST['password']) {
			$style = "background-color: #ff5353;";
		}

		$PHP_SELF = $_SERVER['PHP_SELF'];
	
		$ret .= "<div class=\"login_panel\" style=\"$style\">
	<form method=\"post\" class=\"login_form\" action=\"$PHP_SELF\">
		<b>Login:</b> 
		<label>Username:</label> <input type=\"text\" name=\"username\" id=\"login_user\" />  
		<label>Password:</label> <input type=\"password\" id=\"login_pass\" name=\"password\" /> 
		<input type=\"submit\" value=\"Login\" id=\"login_button\" /> 
	</form>
</div>\n\n";

		return $ret;
	}

	function get_delete_link($filename,$text = "Delete") {
		$PHP_SELF = $_SERVER['PHP_SELF'];
		
		$u_filename = urlencode($filename);
		$ret = "\t<a href=\"$PHP_SELF?show=gallery&amp;delete=$u_filename\" onclick=\"javascript: return confirm('Really delete $filename?'); \">$text</a> ";

		return $ret;
	}

	function resample($file,$quality = 85) {
		#print "Resample: $file $quality";
		# Stub for now!
	}
}

?>