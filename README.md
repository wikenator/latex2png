# latex2png
Latex2PNG converts a given TeX file into a cropped PNG. This conversion script also allows for inclusion of Asymptote graphics into your final PNG.

## Usage
```
php latex2png.php [-h | --help] -i filename.tex [-a | -g] [-w width]
```

Options:
-h, --help		Prints this help message.
-i filename.tex		LaTeX or TeX file to render into PNG.
-a			Used to indicate if the (La)TeX file contains 
			Asymptote code.
-g			Used to indicate if the (La)TeX file contains 
			included graphics. (This is the default option.)
-w width		Specify width of PNG in pixels. (Default is 300.)
