<?php

function	enumerateFilesIn($dir, $regExp, $fn/*(name, full, matches)*/)		{
	$DirEnum = opendir($dir);
	$FilesList = Array();
	while ($CurItem = readdir($DirEnum))		{
		if ($CurItem == "." || $CurItem == "..")			continue;
		$Matches = Array();
		if (strlen($regExp) && !preg_match("@".$regExp."@", $CurItem, $Matches))		continue;
		$FilesList[] = Array("fileName"=>$CurItem, "fullPath"=>$dir."/".$CurItem, "matches"=>$Matches);
	}
	closedir($DirEnum);
	//
	usort($FilesList, function($a, $b)		{	return strnatcmp(strtolower($a["fileName"]), strtolower($b["fileName"]));	});
	foreach($FilesList as $Idx=>$Item)		$fn($Item["fileName"], $Item["fullPath"], $Item["matches"]);
}

function 	validName($str)	{
	return trim(str_replace(Array("/", "&", "?", "!"), Array("_", " and ", "_", "_"), $str));
}

function 	retrievePicturesFromSingleBoard($boardURL)		{
	if (!preg_match("@^https?://.*?4chan.org/([^/]+).*/([0-9]+?)$@sim", $boardURL, $Matches))		return false;
	$BoardSection = $Matches[1];
	$BoardIndex = $Matches[2];
	$DirName = $BoardSection."_".$BoardIndex;
	//
	$FileName = "$DirName.html";
	echo "Download of the board's HTML page : $FileName\n";
	`curl "$boardURL" 2>/dev/null > scraped/$FileName`;
	$Contents = file_get_contents("scraped/$FileName");
	//
	if (!preg_match("@<meta name=\"description\" content=\"(.*?)\"@", $Contents, $Matches))		return false;
	$PageName = $Matches[1];
	$StripPos = strpos($PageName, " - &quot;");
	if ($StripPos !== false)		$PageName = trim(substr($PageName, 0, $StripPos));
	$PageName = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $PageName); 
	$PageName = validName($PageName);
	$NewDirName = $DirName." - $PageName";
	if (file_exists($DirName))			rename($DirName, $NewDirName);
	$DirName = $NewDirName;
	//
	$NbMatches = preg_match_all("@<a href=\"[^\"]*\" target=\"_blank\">(.*?)</a>.*?</div><a class=\"fileThumb\" href=\"(.*?)\" target=\"_blank\"@sim", $Contents, $Matches);
	echo "The board contains $NbMatches image".($NbMatches > 1 ? "s" : "")."\n";
	@mkdir($DirName);
	$Output = str_repeat(".", $NbMatches);
	$NbNewImages = 0;
	echo $Output;
	foreach($Matches[2] as $Idx=>$URL)		{
		$ImageLabel = $Matches[1][$Idx];
		//
		$URL = "http:$URL";
		$URLInfos = pathinfo($URL);
		$ImageName = $URLInfos["basename"];
		$FullImageName = $URLInfos["filename"]."_".validName($ImageLabel);		//###Can ImageLabel be empty ?
		$ShortImagePath = "$DirName/$ImageName";
		$FullImagePath = "$DirName/$FullImageName";
		if (file_exists($ShortImagePath) === false && file_exists($FullImagePath) === false)		{
		 	$ImageContents = file_get_contents($URL);
			file_put_contents($FullImagePath, $ImageContents);
			$NbNewImages++;
			$Output[$Idx] = "+";
		}	else	{
			if (file_exists($ShortImagePath) === true && file_exists($FullImagePath) === false)				rename($ShortImagePath, $FullImagePath);
			$Output[$Idx] = "~";
		}
		echo "\r$Output : ".($Idx+1)." / $NbMatches";
	}
	echo "\n";
	if ($NbNewImages)		echo "$NbNewImages new image".($NbNewImages > 1 ? "s" : "")."\n";
	else					echo "No new images\n";
	return $NbNewImages;
}


if (!isset($_SERVER["argv"][1]))		{
	echo "Please pass the URL of a board to download/refresh all pictures of this board, or --refresh-all to update all previously scraped boards.\n";
	die();
}

$Arg1 = trim($_SERVER["argv"][1]);
if (!preg_match("@^https?://.*?4chan.org/[^/]+/.*$@", $Arg1))		{
	switch($Arg1)	{
		case "--refresh-all":		{
			enumerateFilesIn("scraped", "^(.*?)_(.*).html$", function($name, $full, $matches)		{
				$BoardURL = "http://boards.4chan.org/".$matches[1]."/thread/".$matches[2];
				echo "Refresh of the board's pictures : $BoardURL\n";
				retrievePicturesFromSingleBoard($BoardURL);
			});
			break;
		}
		default:
			die("I need some options to do something...\n");
			break;
	}
}	else	retrievePicturesFromSingleBoard($Arg1);

?>
