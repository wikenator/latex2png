#!/usr/bin/perl

use lib ('.');

use strict;
use warnings;
use PerlAPI qw(removeArrayBlanks removeOuterParens condenseArrayExponents unbalancedCharacter);
use Data::Dumper;

our @latexFunc;
{
	no warnings 'qw';
	@latexFunc = qw(sqrt sinh cosh tanh csch coth sech asin acos atan acsc asec acot log ln abs sin cos tan csc sec cot #sin #cos #tan #csc #sec #cot);
}
our $search_terms = join('|', @latexFunc);

my $debug = 0;
my $cleanExpr = <STDIN>;
chomp($cleanExpr);

$cleanExpr = &cleanParens($cleanExpr, $debug);

print $cleanExpr;

exit();

### Clean String of Parentheses ###############################################
sub cleanParens {
	my $expr = shift;
	my $debug = shift;

	$expr = &cleanSingleParens($expr, $debug);

	# remove outer parentheses around entire expression 
	$expr = &removeOuterParens($expr, $debug);

	# remove parentheses around single pi
	if ($expr !~ /($search_terms)\(pi\)/) {
		$expr =~ s/([^\}\)])\((pi)\)/$1$2/g;
	}

	if ($debug) { print "removed parens: $expr\n"; }

	# ((expr)^(pow)) -> (expr)^(pow)
	if ($expr !~ /($search_terms)\({2}([^\)]*)\)(\^[\(\{]?[^\^]?[\)\}]?)\)/) { $expr =~ s/\({2}([^\)]*)\)(\^[\(\{]?[^\^]?[\)\}]?)\)/($1)$2/g; }

	# remove parens around multiplication section: a*(a^b)*c -> a*a^b*c
	$expr =~ s/^[^\^]\((-?[\w\^\*\(\)]+)\)\*/$1*/g;
	$expr =~ s/\*\((-?[\w\^\*\(\)]+)\)\*/*$1*/g;
	$expr =~ s/\*\((-?[\w\^\*\(\)]+)\)$/*$1/g;
	$expr =~ s/^[^\^]\(([\w\^\*\(\)]+)\)\*/$1*/g;
	$expr =~ s/([\+\-])\(([\w\^\*\(\)]+)\)\*/$1$2*/g;

	$expr = &cleanFractions($expr, $debug);
	$expr =~ s/\({2}(.*?)\)\^\((.*?)\){2}\^/($1)^($2)^/g;

	# remove double parentheses
	# make sure outer parens belonging to exponents are not removed
	if (($expr !~ /\({2}(.*?)\){2}\^/) and
	($expr !~ /\/\({2}(.*?)\){2}/)) {
		my ($d, $l, $r) = 0;
		my $left_flag = 0;
		my $right_flag = 0;
		my $i = 0;

		while ($i < length($expr)) {
			if (substr($expr, $i, 1) eq '(') {
				$d++;

				if (substr($expr, $i-1, 1) eq '(') { $left_flag = 1; }

			} elsif (substr($expr, $i, 1) eq ')') {
				$d--;

				if (substr($expr, $i-1, 1) eq ')') { $right_flag = 1; }
			}

			if ($left_flag) {
				$l = $i;
				$left_flag = 0;
			}

			if ($right_flag) {
				$r = $i;
				$right_flag = 0;
			}

			if (($l and $r) and
			($l < $r)) {
				if ($debug) { print "dbl paren expr: " . substr($expr, $l, $r-$l) . "\n"; }

				$expr = substr($expr, 0, $l-1) . substr($expr, $l, $r-$l) . substr($expr, $r+1);
		#		$l = $r + 1;
				$l = $r = 0;
			}

			$i++;
		}
	}

	if (&unbalancedCharacter($expr, '(', ')', $debug) != 0) { $expr = "($expr)"; }

	if ($debug) { print "removed double parens: $expr\n"; }

	# remove unnecessary parentheses
	## added \( and \) around internal $1 6/18/14
	$expr =~ s/(\([^(\^|\/|$search_terms)(\^\(\d+\)|\^\(?\d\)?)?]\))\(([^\+\-\(\)]+?)\)/$1/g;

	$expr = &removeButtingParens($expr, $debug);

	return $expr;
}

sub cleanSingleParens {
	my $expr = shift;
	my $debug = shift;
	my $latexExpr = [split(/($search_terms|\+|\-)/, $expr)];

	$latexExpr = &removeArrayBlanks($latexExpr, $debug);
	my $arraySize = scalar @$latexExpr;

	if ($debug) { print "removing single parens\n"; }

	for (my $i = 0; $i < $arraySize; $i++) {
		if (grep(/\Q$latexExpr->[$i]\E/, @latexFunc)) {
			if ($debug) { print "function found\n"; }

			next;
		}

		if ($i == 0) {
			if ($debug) { print "first index\n"; }

			$latexExpr->[$i] =~ s/\((.!?)\)/$1/g;

			if (($latexExpr->[$i] !~ /[\^\-]\(.{2,}\)/) and
			($latexExpr->[$i] !~ /\(.{2,}\)\^/)) {
				$latexExpr->[$i] =~ s/\((-?\d*\.?\d{2,}!?)\)/$1/g;
				$latexExpr->[$i] =~ s/\((-?[\w\^]+)\)/$1/g;

			} elsif ($latexExpr->[$i] =~ /\(-?\d*\.?\d+\)\^/) {
				$latexExpr->[$i] =~ s/\((-?\d*\.?\d+)\)/$1/g;
			}

		} elsif (grep(/\Q$latexExpr->[$i-1]\E/, @latexFunc)) {
			if ($debug) { print "previous entry is function\n"; }

			my $k = $i;
			my $left_paren_count = ($latexExpr->[$k] =~ tr/\(//);
			my $right_paren_count = ($latexExpr->[$k] =~ tr/\)//);
			my $delim_count = $left_paren_count - $right_paren_count;
			my $f_arg = '';

			while ($delim_count > 0) {
				$f_arg .= $latexExpr->[$k];
				$k++;

				$delim_count += ($latexExpr->[$k] =~ tr/\(//);
				$delim_count -= ($latexExpr->[$k] =~ tr/\)//);
			}

			$f_arg .= $latexExpr->[$k];
			splice @$latexExpr, $i, $k-$i+1, $f_arg;
			$arraySize = scalar @$latexExpr;

			my $func_arg = &removeOuterParens($f_arg, $debug);

			if ($f_arg ne $func_arg) {
				$func_arg =~ s/([\*\^\/\+\-])\((.)\)/$1$2/g;
				$func_arg =~ s/\((.!)\)/$1/g;

				# factorials, exponents, and expressions containing powers
				if (($func_arg !~ /[\^\-]\(.{2,}\)/) and
				($func_arg !~ /\(.{2,}\)\^/)) {
					$func_arg =~ s/\((-?\d*\.?\d{2,}!?)\)/$1/g;
					$func_arg =~ s/([\*\^\/\+\-])\((-?[\w\*]+)\)/$1$2/g;

				# numbers
				} elsif ($func_arg =~ /\(-?\d*\.?\d+\)\^/) {
					$func_arg =~ s/\((-?\d*\.?\d+)\)/$1/g;
				}

				$latexExpr->[$i] = "($func_arg)";
			}

			if ($debug) { print "func arg: $latexExpr->[$i]\n"; }

			splice @$latexExpr, $i-1, 2, ($latexExpr->[$i-1] . $latexExpr->[$i]);
			$i--;
			$arraySize = scalar @$latexExpr;

		} else {
			if ($debug) { print "removing parens: $latexExpr->[$i]\n"; }

			$latexExpr->[$i] =~ s/\((.!?)\)/$1/g;

			if ($debug) { print "single parens removed\n"; }

			# factorials, exponents, and expressions containing powers
			if (($latexExpr->[$i] !~ /[\^\-]\(.{2,}\)/) and
			($latexExpr->[$i] !~ /\(.{2,}\)\^/)) {
				$latexExpr->[$i] =~ s/\((-?\d*\.?\d{2,}!?)\)/$1/g;
				$latexExpr->[$i] =~ s/\((-?[\w\^]+)\)/$1/g;

			# numbers
			} elsif ($latexExpr->[$i] =~ /\(-?\d*\.?\d+\)\^/) {
				$latexExpr->[$i] =~ s/\((-?\d*\.?\d+)\)/$1/g;
			}

			splice @$latexExpr, $i-1, 2, ($latexExpr->[$i-1] . $latexExpr->[$i]);
			$i--;
			$arraySize = scalar @$latexExpr;
		}

		if ($debug) { 
			print "i: $i\tarray size: $arraySize\n";
			print "$i: $latexExpr->[$i]\n";
			print Dumper($latexExpr);
		}
	}

	return join('', @$latexExpr);
}

sub cleanFractions {
	my $expr = shift;
	my $debug = shift;
	my $i = 0;
	my $latexExpr = [split(/(\(|\)|\/)/, $expr)];

	$latexExpr = &removeArrayBlanks($latexExpr, $debug);
	$latexExpr = &condenseArrayExponents($latexExpr, $debug);

	if ($debug) { print Dumper($latexExpr); }

	while (grep(/^\/$/, @$latexExpr)) {
		my $arraySize = scalar @$latexExpr;
		my $firstPass = 2;

		if (($i >= $arraySize-1) or (($arraySize == 3) and ($latexExpr->[1] eq '/'))) { last; }

		if ($debug) {
			print "removing fraction numerator parens\n";
			print Dumper($latexExpr);
		}

		for (; $i < $arraySize; $i++) {
			if (not($firstPass)) { last; }

			if ($debug) {
				print "first numer character: $latexExpr->[$i]\n";
				print Dumper($latexExpr);
			}

			if (($latexExpr->[$i] eq '(') and
			((not(grep(/\Q$latexExpr->[$i-1]\E/, @latexFunc)) and 
			($latexExpr->[$i-1] !~ /($search_terms)$/)) or
			($i == 0))) {
				my $delim_count = 1;
				my $j = $i+1;
				my $subNumerExpr = '';
	
				while ($delim_count > 0) {
					if ($latexExpr->[$j] eq '(') { $delim_count++; }
					elsif ($latexExpr->[$j] eq ')') { $delim_count--; }

					if ($delim_count > 0) { $subNumerExpr .= $latexExpr->[$j]; }
					else { last; }

					$j++;
				}

				$subNumerExpr = &removeOuterParens($subNumerExpr, $debug);
				$delim_count = 0;

				if ($debug) { print "sub numer expr: $subNumerExpr\n"; }

				if (($j+1 < $arraySize) and
				($latexExpr->[$j+1] ne '/')) {
					splice @$latexExpr, $i, $j-$i+1, $latexExpr->[$i] . $subNumerExpr . $latexExpr->[$j];

					$arraySize = scalar @$latexExpr;

				} elsif ($subNumerExpr =~ /\//) {
					$subNumerExpr = &cleanParens($subNumerExpr, $debug);

					if ($debug) { print "after second numer paren cleaning: $subNumerExpr\n"; }

					if (($subNumerExpr !~ /\(?[^\-+]\)?\^\(?\w+\/?\w*?\)?\//) or
					($subNumerExpr =~ /[^({]+[+\-][^)}]+/)) {
						$subNumerExpr = "($subNumerExpr)";
					}

					splice @$latexExpr, $i, $j-$i+1, $subNumerExpr;
					$arraySize = scalar @$latexExpr;

				} elsif (&unbalancedCharacter($subNumerExpr, '(', ')', $debug) == 0) {
					my $subSubExpr = [split(/(\(|\)|\+|\-)/, $subNumerExpr)];
					my $innerExpr = 0;
					my $subNumerExpr = '';
					my $removeParens = 1;
					$firstPass--;
				
					$subSubExpr = &removeArrayBlanks($subSubExpr, $debug);
					my $subArraySize = scalar @$subSubExpr;

					if ($debug) {
						print "sub sub numer expr of size $subArraySize\n";
						print Dumper($subSubExpr);
					}

					my $k = 0;

					for (; $k < $subArraySize; $k++) {
						if ((($subSubExpr->[$k] eq '+') or
						(($subSubExpr->[$k] eq '-') and
						($k > 0))) and 
						(not($innerExpr))) {
							if ($debug) { print "numerator needs the parens\n"; }

							my $l = $k;
							$removeParens = 0;

							while ($l < $subArraySize) {
								$subNumerExpr .= $subSubExpr->[$l];
								$l++;
							}

							splice @$latexExpr, $i, $j-$i+1, "($subNumerExpr)";
							$arraySize = scalar @$latexExpr;

							last;

						} elsif ($subSubExpr->[$k] eq '(') {
							if ($debug) { print "inner expr found\n"; }

							$innerExpr = 1;
							$delim_count++;

						} elsif ($subSubExpr->[$k] eq ')') {
							if ($debug) { print "leaving inner expr\n"; }

							$delim_count--;
	
							if ($delim_count == 0) { $innerExpr = 0; }

						} elsif ($latexExpr->[$j-3] eq '^') {
							$subNumerExpr = "(" . join('', @$subSubExpr) . ")";

							last;
						}

						$subNumerExpr .= $subSubExpr->[$k];
					}

					if (($subNumerExpr =~ /[\*\+\-]/) and
					($latexExpr->[$i-1] =~ /\/$/)) {
						$subNumerExpr = "($subNumerExpr)";
					}

					if ($removeParens) {
						splice @$latexExpr, $i, $j-$i+1, $subNumerExpr;
						$arraySize = scalar @$latexExpr;
					}
				}

			} elsif ($latexExpr->[$i] eq '/') {
				last;
			}
		}

		if ($debug) { print "numer paren check done, moving on to denom\n"; }

		$arraySize = scalar @$latexExpr;
		$firstPass = 2;
		$i++;

		# remove denominator parens
		for (; $i < $arraySize; $i++) {
			if (not($firstPass)) { last; }

			if ($debug) {
				print "first denom character: $latexExpr->[$i]\n";
				print Dumper($latexExpr);
			}

			if ($latexExpr->[$i] ne '(') {
				splice @$latexExpr, $i-1, 2, $latexExpr->[$i-1] . $latexExpr->[$i];
				$arraySize = scalar @$latexExpr;
				$i--;

				last;

			} else {
				my $delim_count = 1;
				my $j = $i+1;
				my $subDenomExpr = '';

				while ($delim_count > 0) {
					if ($latexExpr->[$j] eq '(') { $delim_count++; }
					elsif ($latexExpr->[$j] eq ')') { $delim_count--; }

					if ($delim_count > 0) { $subDenomExpr .= $latexExpr->[$j]; }
					else { last; }

					$j++;
				}
	
				$subDenomExpr = &removeOuterParens($subDenomExpr, $debug);
				$delim_count = 0;

				if ($debug) { print "sub denom expr: $subDenomExpr\n"; }
	
				if (&unbalancedCharacter($subDenomExpr, '(', ')', $debug) == 0) {
					my $subSubExpr = [split(/(\(|\)|\+|\-|\*|\/)/, $subDenomExpr)];
					my $innerExpr = 0;
					my $subDenomExpr = '';
					my $removeParens = 1;
					$firstPass--;
				
					$subSubExpr = &removeArrayBlanks($subSubExpr, $debug);
					my $subArraySize = scalar @$subSubExpr;

					if ($debug) {
						print "sub sub denom expr of size $subArraySize\n";
						print Dumper($subSubExpr);
					}

					for (my $k = 0; $k < $subArraySize; $k++) {
						if ((($subSubExpr->[$k] eq '+') or
						($subSubExpr->[$k] eq '-') or
						($subSubExpr->[$k] eq '*') or
						($subSubExpr->[$k] eq '/')) and 
						(not($innerExpr))) {
							if ($debug) { print "denominator needs the parens\n"; }

							my $l = $k;
							$removeParens = 0;

							while ($l < $subArraySize) {
								$subDenomExpr .= $subSubExpr->[$l];
								$l++;
							}

							if ($subDenomExpr =~ /\//) {
								$subDenomExpr = &cleanParens($subDenomExpr, $debug);

								if ($debug) { print "after second denom paren cleaning: $subDenomExpr\n"; }
							}

							splice @$latexExpr, $i, $j-$i+1, "($subDenomExpr)";
							$arraySize = scalar @$latexExpr;

							last;

						} elsif ($subSubExpr->[$k] eq '(') {
							if ($debug) { print "inner expr found\n"; }

							$innerExpr = 1;
							$delim_count++;

						} elsif ($subSubExpr->[$k] eq ')') {
							if ($debug) { print "leaving inner expr\n"; }

							$delim_count--;

							if ($delim_count == 0) { $innerExpr = 0; }
						}
	
						$subDenomExpr .= $subSubExpr->[$k];
					}

					if ($removeParens) {
						splice @$latexExpr, $i, $j-$i+1, $subDenomExpr;
						$arraySize = scalar @$latexExpr;
					}
				}
			}
		}

		$expr = join('', @$latexExpr);

		if ($debug) { print "removed parens 2: $expr\n"; }
	
		# remove parentheses around fraction numerator for special cases
#		if ($expr =~ /^\({2}(.*?)\)\^\(.*\){2}\//) { $expr =~ s/\((.*?)\)\//$1\//g; }
		$expr =~ s#\({2}(\d+)\)\/(.*?)\)#($1/$2)#g;

		# remove parentheses around exponent term in numerator
#		if ($expr =~ /\([^\+\-]+?\^((\(\d+(\/\d+)?\))|(\d+))\)\//) { $expr =~ s/\(([^\+\-]+?\^((\(\d+(\/\d+)?\))|(\d+)))\)\//$1\//g; }
	
		if ($debug) { print "removed parens 2.5: $expr\n"; }

		# remove parentheses around fraction denominator
		$expr =~ s/(?<=\/)\(((?!-?\d!?)[^\(\+\-\*\/]+)\)/$1/g; # keep parens for \d[var]
		$expr =~ s/(?<=\/)\((-?\d+!?)\)/$1/g; # remove parens for just digits

		if ($debug) { print "removed parens 3: $expr\n"; }

		# remove parentheses around fractions not 'attached' to surrounding expression
		if (($expr !~ /($search_terms|\^|\/)(\^[\{\(]?\d+[\}\)]?)?\([a-zA-Z0-9]+\/[a-zA-Z0-9]+\)/) and
		($expr !~ /\([a-zA-Z0-9\+\-\/]+\)\^/)) {
			$expr =~ s/([a-zA-Z\+\-]?)\(([a-zA-Z0-9]+\/[a-zA-Z0-9]+)\)([a-zA-Z\+\-]?)/$1$2$3/g;
			$expr =~ s/^\(-?([a-zA-Z0-9]+\/[a-zA-Z0-9]+)\)([a-z\+\-]?)/-$1$2/g;
			$expr =~ s/([\+\-])\(-?([a-zA-Z0-9]+\/[a-zA-Z0-9]+)\)([a-z\+\-]?)/$1-$2$3/g;
		}

		if ($debug) { print "removed parens 4: $expr\n"; }
	}

	return $expr;
}

sub removeButtingParens {
	my $expr = shift;
	my $debug = shift;

	if ($expr =~ /(\)\*?\()/) {
		my $delim_count_j = -1;
		my $delim_count_n = 1;
		my $i = $-[1];
		my $j = $i-1;
		my $m = $+[1]-1;
		my $n = $m+1;
		my $subLeftExpr = substr $expr, $i, 1;
		my $subRightExpr = substr $expr, $m, 1;

		while ($delim_count_j < 0) {
			if ((substr $expr, $j, 1) eq ')') { $delim_count_j--; }
			elsif ((substr $expr, $j, 1) eq '(') { $delim_count_j++; }

			$subLeftExpr = (substr $expr, $j, 1) . $subLeftExpr;
			
			if ($delim_count_j >= 0) { last; }
			
			$j--;
		}

		if ($debug) { print "sub left expr: $subLeftExpr\n"; }

		while ($delim_count_n > 0) {
			if ((substr $expr, $n, 1) eq '(') { $delim_count_n++; }
			elsif ((substr $expr, $n, 1) eq ')') { $delim_count_n--; }

			$subRightExpr .= (substr $expr, $n, 1);

			if ($delim_count_n <= 0) { last; }

			$n++;
		}

		if ($debug) { print "sub right expr: $subRightExpr\n"; }

		my $left_flag = 2;
		my $override = 0;
		my $k;

		if ($j-3 < 0) {
			if ($j-2 < 0) { $k = 1; }
			else { $k = 2; }

		} else {
			$k = 3;
		}

		if (((substr $expr, $j-$k-1, $k+1) !~ /($search_terms|\^|\/)/) and
		($subLeftExpr !~ /[\+\-]/)) {
			$subLeftExpr = &removeOuterParens($subLeftExpr, $debug);
			$left_flag = 0;
		}

		if ((substr $expr, $j-$k-2, $k+1) !~ /($search_terms)/) { $override = 1; }

		if ((not($left_flag) or $override) and ($subRightExpr !~ /[\+\-]/)) { $subRightExpr = &removeOuterParens($subRightExpr, $debug); }

		if ($debug) {
			print "sub left expr: $subLeftExpr\n";
			print "sub right expr: $subRightExpr\n";
		}

		substr $expr, $j, $i-$j+1, $subLeftExpr;
		
		if ($debug) { print "left replacement: $expr\n"; }

		substr $expr, $m-2+$left_flag, $n-$m+1, $subRightExpr;

		if ($debug) { print "right replacement: $expr\n"; }
	}

	if ($debug) { print "unnecessary paren removal: $expr\n"; }

	return $expr;
}
