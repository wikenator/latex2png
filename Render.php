<?php
class Render {
	private $asy_images = '';
	private $problem_section;

	public function __construct($asy_images = false) {
		$this->asy_images = $asy_images;
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

\\begin{document}
	$thunk
\\end{document}
EOS;
	}// end wrap

	/*
	* Purpose: transform raw code from file to TeX-renderable code
	*
	* @param string text: original problem containing TeX code
	* @param integer width: final width of rendered TeX image in pixels
	*
	* @return string: text containing TeX code replaced by image tags
	*/
	public function transform($text, $width) {
		$asyHash = '';

		if ($this->asy_images)
			require_once('Asymptote.php');

		// change \$ to #dol temporarily
		$text = preg_replace("/\\\\\\$/", "#dol", $text);

		if ($this->asy_images) {
			// render asymptote to EPS
			$asy = new Asymptote();
			list($text, $asyHash) = $asy->transform($text);
			unset($asy);
		}// end if

		// create cache directory if DNE, then process latex
		if (is_dir(Config::CACHE_DIR) ||
		mkdir(Config::CACHE_DIR, 0755, true)) {
			$thunk = "\\begin{varwidth}{{$width}px}$text\\end{varwidth}";
			$thunk = "$\\textrm{" . $thunk . "}$";

			// replace #dol with \$ again
			$thunk = preg_replace("/#dol/", "\\\\$", $thunk);

			// append time to thunk before md5 for better randomization
			$hash = md5($thunk . time());
			$full_name = Config::CACHE_DIR . "/$hash.png";

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
		chdir(Config::TMP_DIR);

		// create temporary LaTeX file
		#$fp = fopen(Config::TMP_DIR . "/$hash.tex", "w+");
		$fp = fopen("./$hash.tex", "w+");
		fputs($fp, $thunk);
		fclose($fp);

		// convert from latex -> dvi -> ps -> png
		if ($this->asy_images)
			$command = Config::LATEX_PATH . " --shell-escape --interaction=nonstopmode $hash.tex; " . Config::DVIPS_PATH . " -E $hash -o $hash.ps; " . Config::CONVERT_PATH . " -density 850 $hash.ps -colorspace RGB -filter Lanczos -resize 20% -colorspace sRGB $hash.png";
		
		// convert from latex -> dvi -> png
		else
			$command = Config::LATEX_PATH . " --shell-escape $hash.tex; " . Config::DVIPNG_PATH . " -q -T tight -D 170 -bg Transparent $hash -o $hash.png";// > /dev/null 2>&1";

		exec($command);

		chdir($current_dir);

		// copy the file to the cache directory
		copy(Config::TMP_DIR . "/$hash.png", Config::CACHE_DIR . "/$hash.png");
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
		chdir(Config::TMP_DIR);
		file_exists("$hash.tex") && unlink("$hash.tex");
		file_exists("$hash.aux") && unlink("$hash.aux");
		file_exists("$hash.log") && unlink("$hash.log");
		file_exists("$hash.dvi") && unlink("$hash.dvi");
		file_exists("$hash.png") && unlink("$hash.png");

		if ($this->asy_images)
			file_exists("$hash.ps") && unlink("$hash.ps");

		if ($asyHash != '')
			file_exists("$asyHash.eps") && unlink("$asyHash.eps");

		chdir($current_dir);
	}// end cleanup
}// end Render class
