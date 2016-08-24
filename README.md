# DeTeXify
DeTeXify removes LaTeX and TeX tags from an input string and return a computer-evaluatable string.

## Examples:
- \\frac{1}{2} => 1/2
- \\sqrt{2} => 2^(1/2)
- \\frac{x+y}{3} => (x+y)/3

### DeTeXify currently handles the following LaTeX and TeX tags with infinite recursion (conversion):
- \\frac{a}{b} => a/b
- \\sqrt{a} => a^(1/2)
- \\sqrt[a]{b} => b^(1/a)
- a^-b => a^(-b)
- \\ln(a), \\log(b) => ln(a), log(b)
- trigonometric functions (regular, inverse, and hyperbolic) e.g. \sin(a) => sin(a)
- \\abs(a) => abs(a)
- \\lbrack, \\rbrack => [, ]
- \\pi => pi
- \\infty => inf
- \\emptyset => null
- \\exp(a) => e^a
- \\% => %
- \\[, \\] => $$
- \\{, \\} => (, )

### DeTeXify currently handles the following LaTeX and TeX tags (removal):
- \\break
- \\displaystyle
- \\left, \\right
- \\circ
- \\operatorname
- \\immediate
- \\write18
- \\write
- \\$
- leading and trailing spaces
