<?php
	$in = fopen("debug.log","r");
	$out = false;
	
	$last=0;
	$group=1;
	while(!feof($in)) {
		$line = fgets($in);
		if(preg_match("/ Running test ([[:digit:]]+): ([^[:space:]]+)/", $line, $matches)) {
			if($out) fclose($out);

			$nr = $matches[1];
			if($nr<$last) $group++;
			$last=$nr;

			$out = fopen("{$group}_{$nr}_$matches[2].log","w");
		}
		if($out) fputs($out, $line);
	}
	fclose($in);
	fclose($out);
?>
