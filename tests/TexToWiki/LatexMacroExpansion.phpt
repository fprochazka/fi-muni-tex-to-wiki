<?php

use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

$macro = \TexToWiki\Mediawiki\Configurator::configureMB102();

Assert::same(
	<<<'TEX'
  \begin{tabular}{|c||c|c|c|c|c|c|c|}
    \hline
     & a_n & \ a_{n-1}\  & \ a_{n-2}\  & \ a_{n-3}\  & \ \dots\  & \ a_1\  & \ a_0\  \\ \hline
     x_0 & b_0:=a_n & b_1 & b_2 & b_3 & \dots & b_{n-1} & b_n=f(x_0) \\ \hline
  \end{tabular}
TEX
	, $macro->expand(
		<<<'TEX'
  \begin{tabular}{|c||c|c|c|c|c|c|c|}
    \hline
     & $a_n$ & \ $a_{n-1}$\  & \ $a_{n-2}$\  & \ $a_{n-3}$\  & \ $\dots$\  & \ $a_1$\  & \ $a_0$\  \\ \hline
     $x_0$ & $b_0:=a_n$ & $b_1$ & $b_2$ & $b_3$ & \dots & $b_{n-1}$ & $b_n=f(x_0)$ \\ \hline
  \end{tabular}
TEX
	)
);

Assert::same(
	<<<'TEX'
\fbox{$\displaystyle \, 
   \begin{array}{l}
	 \text{$f(x)$ je konvexní na $[\pi,2\pi]$ a na $[3\pi,4\pi]$}, \\[1mm]
	 \text{$f(x)$ je konkávní na $[0,\pi]$ a na $[2\pi,3\pi]$}, \\[1mm]
	 \text{$f(x)$ má inflexi v bodech $x=\pi,2\pi,3\pi$}.
   \end{array}
 \, $}\,
TEX
	, $macro->expand(
		<<<'TEX'
\mathbox{
   \begin{array}{l}
	 \text{$f(x)$ je konvexní na $[\pi,2\pi]$ a na $[3\pi,4\pi]$}, \\[1mm]
	 \text{$f(x)$ je konkávní na $[0,\pi]$ a na $[2\pi,3\pi]$}, \\[1mm]
	 \text{$f(x)$ má inflexi v bodech $x=\pi,2\pi,3\pi$}.
   \end{array}
}
TEX
	)
);

Assert::same(
	<<<'TEX'
    \begin{align*}
      \int x\,\cos x\,\mathrm{d}x &\quad\bigg| \begin{array}{ll}
  u'=\cos x \quad & u=\sin x \\
  v=x \quad & v'=1
\end{array} \bigg| = x\,\sin x-\int\sin x\,\mathrm{d}x \\[1mm]
      &= x\,\sin x-(-\cos x)+C = \fbox{$\displaystyle \, x\,\sin x+\cos x+C \, $}\,.
    \end{align*}
TEX
	, $macro->expand(
		<<<'TEX'
    \begin{align*}
      \int x\,\cos x\,\dx &\perpartes{\cos x}{\sin x}{x}{1} = x\,\sin x-\int\sin x\,\dx \\[1mm]
      &= x\,\sin x-(-\cos x)+C = \mathbox{x\,\sin x+\cos x+C}.
    \end{align*}
TEX
	)
);

Assert::same(
	<<<'TEX'
  \begin{equation} 
    \lim_{n\to\infty} \sqrt[n]{n}=\lim_{n\to\infty} n^{\frac{1}{n}} = \lim_{n\to\infty} \mathrm{e}^{\frac{1}{n}\,\ln n}
    \overset{\text{Věta~(ii)}}{=} \mathrm{e}^{\lim_{n\to\infty}\frac{\ln n}{n}}
    \quad\bigg| \text{ typ } \frac{\infty}{\infty}\ \bigg| \overset{\text{l'Hosp.}}{=} \mathrm{e}^{\lim_{n\to\infty}\frac{\frac{1}{n}}{1}} = \mathrm{e}^0=1.
  \end{equation}
TEX
	, $macro->expand(
		<<<'TEX'
  \begin{equation} \label{E:limita.n.sqrt.n}
    \lim_{n\to\infty} \sqrt[n]{n}=\lim_{n\to\infty} n^{\frac{1}{n}} = \lim_{n\to\infty} \e^{\frac{1}{n}\,\ln n}
    \overset{\text{Věta~\ref{T:vlastnosti.spojitych.funkci}(ii)}}{=} \e^{\lim_{n\to\infty}\frac{\ln n}{n}}
    \biggtyp{\frac{\infty}{\infty}} \overset{\text{l'Hosp.}}{=} \e^{\lim_{n\to\infty}\frac{\frac{1}{n}}{1}} = \e^0=1.
  \end{equation}
TEX
	)
);

Assert::same(
	<<<'TEX'
 \int_1^\infty \frac{1}{x^p}\,\mathrm{d}x = \left\{ \!
  \begin{array}{ll}
	\infty, \qquad & \text{pro p<1, tj. tento nevlastní integrál diverguje k \infty}, \\[1mm]
	\frac{1}{p-1}, \qquad & \text{pro p>1, tj. tento nevlastní integrál konverguje}.
  \end{array} \right. 
TEX
	, $macro->expand(
		<<<'TEX'
 \int_1^\infty \frac{1}{x^p}\,\mathrm{d}x = \left\{ \!
  \begin{array}{ll}
	\infty, \qquad & \text{pro p<1, tj. tento nevlastní integrál diverguje k \infty}, \\[1mm]
	\frac{1}{p-1}, \qquad & \text{pro p>1, tj. tento nevlastní integrál konverguje}.
  \end{array} \right. 
TEX
	)
);

Assert::same(
	<<<'TEX'
 \sum_{n=1}^\infty \frac{1}{n^p} \quad
      \fbox{$\displaystyle \, \text{diverguje k \infty pro p\leq1 a konverguje pro p>1} \, $}\,. 
TEX
	, $macro->expand(
		<<<'TEX'
 \sum_{n=1}^\infty \frac{1}{n^p} \quad
      \fbox{$\displaystyle \, \text{diverguje k \infty pro p\leq1 a konverguje pro p>1} \, $}\,. 
TEX
	)
);

Assert::same('[0,1)', $macro->expand('[0,1)'));

Assert::same('x\in[0,9)', $macro->expand('x\in[0,9)'));

Assert::same('\mathcal{R}', $macro->expand('\\R'));
