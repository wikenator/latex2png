<?php
class Latex2PNG {
	private $latex = '';
	private $asy_images = '';
	private $width = '';
	private $syntax_err = array();

	/*
	* Purpose: if variable latex is set, initialize variables
	*
	* @param DOMDocument xmlNode: xml node containing all object data
	*
	* @return Latex2PNG object
	*/
	public function __construct($latex_file = '', $img_width = 300, $asy_images = false) {
		if (!empty($latex_file)) {
			$this->latex = file_get_contents($latex_file);
			$this->width = $img_width;
			$this->asy_images = $asy_images;

			if ($this->latex)
				$this->processImage();

			else
				echo "Unable to read $latex_file.\n";

		} else {
			echo "No file specified.\n";
		}// end if
	}// end constructor

	public function __set($property, $value) {
		if (property_exists($this, $property))
			$this->$property = $value;

		return $this;
	}// end magic setter

	public function __get($property) {
		if (property_exists($this, $property))
			return $this->$property;
	}// end magic getter

	/*
	* Purpose: add problem to database, or update problem in database
	*
	* @return null
	*/
	public function processImage() {
		if (file_exists(Config::CHKTEX_PATH))
			$this->cleanLatex();

		// transform latex code into image
		$this->processLatex();

		return $this;
	}// end processDb

	/*
	* Purpose: remove malicious and unnecessary characters from latex
	*
	* @return null
	*
	*/
	private function cleanLatex() {
		// remove script tags
		$this->latex = preg_replace("/(\s+?)<script.*?>.*?<\/script>(\s+)?/", '', $this->latex);

		// remove latex tags to prevent possible XSS attacks
		$this->latex = preg_replace("/\\\\immediate/", '', $this->latex);
		$this->latex = preg_replace("/\\\\write18/", '', $this->latex);
		$this->latex = preg_replace("/\\\\write/", '', $this->latex);

		// remove multiple space characters
		$this->latex = preg_replace('/[ \t]+/', " ", trim($this->latex));

		// remove illegal unicode characters
		$this->latex = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $this->latex);
	}// end cleanLatex

	/*
	* Purpose: check latex syntax
	*
	* @return boolean: true for valid syntax, false otherwise
	*/
	public function syntaxCheck() {
		# use "-n [number]" switch to ignore chktex errors
		$command = Config::CHKTEX_PATH . " -qv0 temp.tex";

		foreach (array($this->q, $this->s, $this->c) as $latex) {
			// 'mask' asymptote code to avoid false positives
			// remove newlines to avoid input cutoff
			$this->latex = str_replace("\r", '', $this->latex);
			$this->latex = str_replace("\n", '', $this->latex);

			if ($this->asy_images) {
				// find all asymptote code sections
				preg_match_all("/(\\\\begin\\{asy\\}.*?\\\\end\\{asy\\})/", $this->latex, $matches);

				// replace asymptote code with nothing
				for ($i = 0; $i < count($matches[0]); $i++) {
					$position = strpos($this->latex, $matches[0][$i]);
					$this->latex = substr_replace($this->latex, '', $position, strlen($matches[0][$i]));
				}// end for
			}// end if

			// create temporary LaTeX file
			$fp = fopen('temp.tex', 'w+');
			fputs($fp, $this->latex);
			fclose($fp);

			exec($command, $this->syntax_err);
			unlink('temp.tex');

			if (!empty($this->syntax_err)) {
				for ($i = 0; $i < count($this->syntax_err); $i++) {
					// err_entries indices:
					// 0: filename (temp.tex)
					// 1: line number
					// 2: column number
					// 3: chktex error number
					// 4: error message
					$err_entries = explode(':', $this->syntax_err[$i]);

					$this->syntax_err[$i] = "{$latexSection[$j]} error {$err_entries[3]} on line {$err_entries[1]}: {$err_entries[4]}";

					if (count($matches[0]))
						$this->syntax_err[$i] .= " [Asymptote Code Ignored]";
				}// end foreach
			}// end if

			$j++;
		}// end foreach

		return true;
	}// end syntaxCheck

	/*
	* Purpose: render latex tags to images
	*
	* @return array: problem object
	*/
	private function processLatex() {
		require_once('Render.php');
		$render = new Render($this->asy_images);

		// render TeX to PNG
		$this->latex = $render->transform($this->latex, $this->width);

		unset($render);

		return $this;
	}// end processLatex
}// end Latex2PNG class
