<?php
class Render {
	private $LATEX_PATH = "/usr/bin/latex";
	private $DVIPS_PATH = "/usr/bin/dvips";
	private $CONVERT_PATH = "/usr/bin/convert";
	private $TMP_DIR ="./tmp";

	private $CACHE_DIR = "";
	private $URL_PATH = "";
	private $problem_section;
	private $img_width;

	public function __construct($img_width = 300) {
		$this->img_width = $img_width;
	}// end constructor

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
\\usepackage{enumitem}
\\usepackage{cancel}
\\usepackage{textcomp}
\\usepackage{wasysym}
\\usepackage{BinaryDiv}
%\\usepackage[paperheight=\\maxdimen]{geometry}
\\DeclareGraphicsExtensions{.eps}
\\input{longdiv}
\\pagestyle{empty}
\\thispagestyle{empty}

\\definecolor{hebg}{RGB}{246,255,255}

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
	public function transform($text, $path) {
		require_once('Asymptote.php');

		// replace \begin{tex} and \end{tex} tags with $
		#$text = preg_replace("/\\\\begin\\{tex\\}/", "$", $text);
		#$text = preg_replace("/\\\\end\\{tex\\}/", "$", $text);

		// replace [] tags with $$
		#$text = preg_replace("/\\\\\[(.*?)\\\\\]/", "$$ $1 $$", $text);

		// change \$ to #dol temporarily
		$text = preg_replace("/\\\\\\$/", "#dol", $text);

		// change <br/> tags to \\ (TeX newline)
		$text = preg_replace("/(\s+)?<br(\s*\/?)?>(\s+)?/", " \\\\\\\\ ", $text);

		// render asymptote to EPS
		$asy = new Asymptote();
		list($text, $asyHash) = $asy->transform($text, $path);
		unset($asy);

		$this->CACHE_DIR = $path;

		// create cache directory if DNE, then process latex
		if (is_dir($this->CACHE_DIR) ||
		mkdir($this->CACHE_DIR, 0755, true)) {
			$thunk = "\\begin{varwidth}{{$this->img_width}px}$text\\end{varwidth}";
			$thunk = "$\\textrm{" . $thunk . "}$";

			// replace #dol with \$ again
			$thunk = preg_replace("/#dol/", "\\\\$", $thunk);

			// append time to thunk before md5 for better randomization
			$hash = md5($thunk . time());
			$full_name = $this->CACHE_DIR . "/$hash.png";

			// do not overwrite file if exists
			if (!is_file($full_name)) {
				$this->render_latex($thunk, $hash);
				$this->cleanup($hash, $asyHash);
			}// end if
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
		#fp = fopen($this->TMP_DIR . "/$hash.tex", "w+");
		$fp = fopen("./$hash.tex", "w+");
		fputs($fp, $thunk);
		fclose($fp);

		// convert from latex -> dvi -> ps -> png
		$command = $this->LATEX_PATH . " --shell-escape --interaction=nonstopmode $hash.tex; " . $this->DVIPS_PATH . " -E $hash -o $hash.ps; " . $this->CONVERT_PATH . " -density 850 $hash.ps -colorspace RGB -filter Lanczos -resize 20% -colorspace sRGB $hash.png";
		exec($command);

		chdir($current_dir);
		// copy the file to the cache directory
		copy($this->TMP_DIR . "/$hash.png", $this->CACHE_DIR . "/$hash.png");
	}// end render_latex

	/*
	* Purpose: delete intermediary files created from render_latex
	*
	* @param string hash: randomized filename for PNG image
	*
	* @return null
	*/
	private function cleanup($hash, $asyHash) {
		$current_dir = getcwd();
		chdir($this->TMP_DIR);
		file_exists("$hash.tex") && unlink("$hash.tex");
		file_exists("$hash.aux") && unlink("$hash.aux");
		file_exists("$hash.log") && unlink("$hash.log");
		file_exists("$hash.dvi") && unlink("$hash.dvi");
		file_exists("$hash.ps") && unlink("$hash.ps");
		file_exists("$hash.png") && unlink("$hash.png");

		if ($asyHash != '')
			file_exists("$asyHash.eps") && unlink("$asyHash.eps");

		chdir($current_dir);
	}// end cleanup
}// end Render class
