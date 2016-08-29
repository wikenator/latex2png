<?php
class Render2 {
	private $LATEX_PATH = "/usr/bin/latex";
	private $DVIPS_PATH = "/usr/bin/dvips";
	private $CONVERT_PATH = "/usr/bin/convert";

	private $TMP_DIR ="/home/hemath/secure_html/adm/tmp";
	private $CACHE_DIR = "";
	private $URL_PATH = "";

	/*
	* Purpose: add header and document commands to TeX code
	*
	* @param string thunk: TeX code to be rendered
	*
	* @return string: HEREDOC containing fully formatted TeX file
	*/
	private function wrap($thunk) {
return <<<EOS
\\documentclass{standalone}
\\usepackage{mathtools}
\\usepackage{amsfonts}
\\usepackage{amssymb}
\\usepackage{pst-plot}
\\usepackage{color}
\\usepackage{mathrsfs}
\\usepackage{latexsym}
\\usepackage{esint}
\\usepackage{eucal}
\\usepackage{polynom}
\\usepackage{xlop}
\\usepackage{varwidth}
\\usepackage{graphicx}
\\usepackage{cancel}
%\\usepackage[paperheight=\maxdimen]{geometry}
\\DeclareGraphicsExtensions{.eps}
\\input{longdiv}
\\pagestyle{empty}
\\thispagestyle{empty}

\\begin{document}
	$thunk
\\end{document}
EOS;
	}// end wrap

	/*
	* Purpose: transform raw code from file to TeX-renderable code
	*
	* @param string text: original problem containing TeX code
	* @param string path: destination of rendered TeX image
	*
	* @return string: text containing TeX code replaced by image tags
	*/
	public function transform($text, $path, $choice = false) {
		// replace [] tags with $$
		$text = preg_replace("/\\\\\[(.*?)\\\\\]/", "$$ $1 $$", $text);

		// change \$ to #dol temporarily
		$text = preg_replace("/\\\\\\$/", "#dol", $text);

		// change <br/> tags to \\ (TeX newline)
		$text = preg_replace("/(\s+)?<br(\s*\/?)?>(\s+)?/", " \\\\\\\\ ", $text);

		// change img tags to \includegraphics tag
		// first find all image tags
		preg_match_all("#<img .*?/>#", $text, $matches);

//		$doc = new DOMDocument();
//		@$doc->loadHTML(htmlspecialchars($text)) or trigger_error("Could not load HTML: $text\n");
//		$tags = $doc->getElementsByTagName('img');

//		foreach ($tags as $tag) {
		for ($i = 0; $i < count($matches[0]); $i++) {
			// split all src paths on "
//			$src = explode('"', str_replace("'", '"', $tag->getAttribute('src')));
			$position = strpos($text, $matches[0][$i]);
			$src = explode('src', str_replace("'", '"', $matches[0][$i]));
			$src_img = explode('"', $src[1]);

			$image = "/home/hemath/secure_html/adm/cache/contest/{$src_img[1]}";

//			$text = preg_replace("#<img.*?{$tag->getAttribute('src')}.*>#", "\\includegraphics{" . $image . "}", $text);
			$text = substr_replace($text, "\\includegraphics{" . $image . "}", $position, strlen($matches[0][$i]));
		}// end for

		$this->CACHE_DIR = "/home/hemath/secure_html/adm/cache/$path";
		$this->URL_PATH = "https://math.he.net/adm/cache/$path";

		// create cache directory if DNE, then process latex
		if (is_dir($this->CACHE_DIR) ||
		mkdir($this->CACHE_DIR, 0755, true)) {
			if ($choice)
				$thunk = "\\begin{varwidth}{100px}$text\\end{varwidth}";

			else
				$thunk = "\\begin{varwidth}{300px}$text\\end{varwidth}";

			$thunk = "$\\textrm{" . $thunk . "}$";

			// replace #dol with \$ again
			$thunk = preg_replace("/#dol/", "\\\\$", $thunk);

			// append time to thunk before md5 for better randomization
			$hash = md5($thunk . time());
			$full_name = $this->CACHE_DIR . "/$hash.png";
			$url = $this->URL_PATH . "/$hash.png";

			// do not overwrite file if exists
			if (!is_file($full_name)) {
				$this->render_latex($thunk, $hash);
				$this->cleanup($hash);
			}// end if

			$text = "<img src='$url' class='latex' />";
		}// end if

		return $text;
	}// end transform

	/*
	* Purpose: render TeX code into PNG image
	*
	* @param string thunk: fully formatted TeX document string
	* @param string hash: randomized filename for PNG image
	*
	* @return null
	*/
	private function render_latex($thunk, $hash) {
		$thunk = $this->wrap($thunk);
		$current_dir = getcwd();
		chdir($this->TMP_DIR);

		// create temporary LaTeX file
		$fp = fopen($this->TMP_DIR . "/$hash.tex", "w+");
		fputs($fp, $thunk);
		fclose($fp);

		// convert from latex -> dvi -> ps -> png
		$command = $this->LATEX_PATH . " --shell-escape --interaction=nonstopmode $hash.tex; " . $this->DVIPS_PATH . " -E $hash -o $hash.ps; " . $this->CONVERT_PATH . " -density 850 $hash.ps -colorspace RGB -filter Lanczos -resize 20% -colorspace sRGB $hash.png";
		exec($command);

		// copy the file to the cache directory
		copy("$hash.png", $this->CACHE_DIR ."/$hash.png");
		chdir($current_dir);
	}// end render_latex

	/*
	* Purpose: delete intermediary files created from render_latex
	*
	* @param string hash: randomized filename for PNG image
	*
	* @return null
	*/
	private function cleanup($hash) {
		$current_dir = getcwd();
		chdir($this->TMP_DIR);
		file_exists("$hash.tex") && unlink("$hash.tex");
		file_exists("$hash.aux") && unlink("$hash.aux");
		file_exists("$hash.log") && unlink("$hash.log");
		file_exists("$hash.dvi") && unlink("$hash.dvi");
		file_exists("$hash.ps") && unlink("$hash.ps");
		file_exists("$hash.png") && unlink("$hash.png");

		chdir($current_dir);
	}// end cleanup
}// end Render class
