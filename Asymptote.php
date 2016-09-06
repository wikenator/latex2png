<?php
class Asymptote {
	/*
	* Purpose: 
	*
	* @param string text: original string containing asymptote code
	*
	* @return string: text containing asymptote code replaced by image tags
	*/
	public function transform($text) {
		$hash = '';

		// remove newlines to prevent input cutoff
		$text = str_replace("\r", "", $text);
		$text = str_replace("\n", "", $text);

		preg_match_all("/(\\\\begin\\{asy\\}.*?\\\\end\\{asy\\})/", $text, $matches);

		// process asymptote
		for ($i = 0; $i < count($matches[0]); $i++) {
			$position = strpos($text, $matches[0][$i]);
			$ahunk = $matches[1][$i];

			// remove \begin and \end asy tags
			$ahunk = str_replace('\begin{asy}', '', $ahunk);
			$ahunk = str_replace('\end{asy}', '', $ahunk);
				
			$ahunk = "import math; import graph;" . $ahunk;
			$ahunk = str_replace(';', ";\n", $ahunk);

			// append time to ahunk before md5 for better randomization
			$hash = md5($ahunk . time());

			// do not overwrite file if exists
			if (!is_file("$hash.eps"))
				$this->asymp($ahunk, $hash);

			// replace asymptote code with \includegraphics tag
			$text = substr_replace($text, "\\includegraphics{" . $hash . "}", $position, strlen($matches[0][$i]));
		}// end for

		return array($text, $hash);
	}// end transform

	/*
	* Purpose: transform asymptote code into EPS image
	*
	* @param string ahunk: asymptote code
	* @param string hash: randomized filename for EPS image
	*
	* @return null
	*/
	private function asymp($ahunk, $hash) {
		$current_dir = getcwd();
		chdir(Config::TMP_DIR);
		
		// create temporary asymptote file
		$fp = fopen("$hash.asy", "w+");
		fputs($fp, $ahunk);
		fclose($fp);

		// convert from asymptote -> eps 
		$command = Config::ASY_PATH . " -f eps $hash.asy";
		exec($command);

		unlink ("$hash.asy");
		chdir($current_dir);
	}// end asymp
}// end Asymptote class
