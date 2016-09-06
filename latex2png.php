<?php
	require_once('Config.php');
	require_once('Latex2PNG.php');

	$latex_file = '';
	$img_width = 300;
	$asy_flag = false;

	if ($argc <= 2 || in_array($argv, array('--help', '-h'))) {

?>
Usage:
php <?php echo $argv[0]; ?> [-h | --help] -i filename.tex [-a | -g] [-w width]

Options:
-h, --help		Prints this help message.
-i filename.tex		LaTeX or TeX file to render into PNG.
-a			Used to indicate if the (La)TeX file contains 
			Asymptote code.
-g			Used to indicate if the (La)TeX file contains 
			included graphics. (This is the default option.)
-w width		Specify width of PNG in pixels. (Default is 300.)

<?php
	} else {
		for ($i = 0; $i < count($argv)-1; $i++) {
			if ($argv[$i] == '-i')
				$latex_file = $argv[++$i];

			elseif ($argv[$i] == '-a')
				$asy_flag = true;

			elseif ($argv[$i] == '-w')
				$img_width = (int)$argv[++$i];
		}// end foreach

		$l2png = new Latex2PNG($latex_file, $img_width, $asy_flag);
	}// end if
?>
