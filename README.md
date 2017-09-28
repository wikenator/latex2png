# latex2png
Latex2PNG converts a given TeX file into a cropped PNG. This conversion script also allows for inclusion of ![Asymptote](http://asymptote.sourceforge.net/ "Asymptote") graphics into your final PNG.

## Usage
```
php latex2png.php [-h | --help] -i filename.tex [-a | -g] [-w width]
```

**Options:**

| Switch | Details |  
|:------:|:--------|  
| -h, --help | Prints this help message. |  
| -i filename.tex | LaTeX or TeX file to render into PNG. |  
| -a | Used to indicate if the (La)TeX file contains Asymptote code. | 
| -g | Used to indicate if the (La)TeX file contains included graphics. (This is the default option.) |  
| -w width | Specify width of PNG in pixels. (Default is 300.) |  

## Tips
* To include Asymptote images, place your Asymptote code between `\begin{asy}` and `\end{asy}` tags.
* Using the `-a` option will render the image slightly differently because a different conversion method is used (LATEX->DVI->PS->PNG) instead of the more straightforward conversion (LATEX->DVI->PNG). This has resulted in a slightly blurry image due to the necessity of ImageMagick required to convert the PS file to a PNG image. For example:

| PNG | PS->PNG |
|:---:|:---:|
|![DVI-PNG](dvipng_example.png "DVI-PNG") | ![DVI-PS-PNG](dvipng_asy_example.png "DVI-PS-PNG") |

   It is recommended to only use the `-g` option if Asymptote code is not used.

### Asymptote Image Sample
Code:
```
Here is a rectangle:
$$\begin{asy}
        size(4cm);
        draw((0,0)--(20,0)--(20,8)--(0,8)--cycle);
        label("$80$", (20,0)--(20,8), E);
        label("$200$", (0,8)--(20,8), N);
\end{asy}$$
```
Resulting Image:

![Asymptote Image](dvips_asy_example.png "Asymptote Image")
