<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
/**
 * @package info.ajaxplorer
 * Description : Class for handling pl upload
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class PluploadProcessor extends AJXP_Plugin {

// 15 minutes execution time
//@set_time_limit(15 * 60);

// Uncomment this one to fake upload time
// usleep(5000);

	public function unifyChunks($action, &$httpVars, &$fileVars){
			$filename = SystemTextEncoding::fromUTF8($httpVars["name"]);
			$tmpName = $fileVars["file"]["tmp_name"];
			$chunk = $httpVars["chunk"];
			$chunks = $httpVars["chunks"];
			
			//error_log("currentChunk:".$chunk."  chunks: ".$chunks);
			
			$repository = ConfService::getRepository();
			if(!$repository->detectStreamWrapper(false)){
				return false;
			}   	
			$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
			$streamData = $plugin->detectStreamWrapper(true);		
			$dir = $httpVars["dir"];
			$destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/"; 
			
			//error_log("Directory: ".$dir);
			
			// Clean the fileName for security reasons
			//$filename = preg_replace('/[^\w\._]+/', '', $filename);
			
			// Look for the content type header
			if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
				$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

			if (isset($_SERVER["CONTENT_TYPE"]))
				$contentType = $_SERVER["CONTENT_TYPE"];	
			
			// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
			if (strpos($contentType, "multipart") !== false) {
				if (isset($tmpName) && is_uploaded_file($tmpName)) {
					//error_log("tmpName: ".$tmpName);
					
					// Open temp file
					$out = fopen($destStreamURL.$filename, $chunk == 0 ? "wb" : "ab");
					if ($out) {
						// Read binary input stream and append it to temp file
						$in = fopen($tmpName, "rb");

						if ($in) {
							while ($buff = fread($in, 4096))
								fwrite($out, $buff);
						} else
							die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
						fclose($in);
						fclose($out);
						@unlink($tmpName);
					} else
						die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
				} else
					die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
			} else {
				// Open temp file
				$out = fopen($destStreamURL.$filename, $chunk == 0 ? "wb" : "ab");
				if ($out) {
					// Read binary input stream and append it to temp file
					$in = fopen("php://input", "rb");

					if ($in) {
						while ($buff = fread($in, 4096))
							fwrite($out, $buff);
					} else
						die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

					fclose($in);
					fclose($out);
				} else
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
			}
            /* we apply the hook if we are uploading the last chunk */
            if($chunk == $chunks-1)
                AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($destStreamURL.$filename), false));
			// Return JSON-RPC response
			die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
	}
}
?>
