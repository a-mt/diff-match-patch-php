<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

if(isset($_POST['leftTxt'])) {
  require __DIR__ . '/../RichDiff.php';

  // Retrieve _POST data
  $leftTxt  = trim(str_replace("\r", '', $_POST['leftTxt']));
  $rightTxt = trim(str_replace("\r", '', $_POST['rightTxt']));

  $dmp = new RichDiff();
  echo $dmp->rich_diff($leftTxt, $rightTxt);
  die;
}

//+-----------------------------------------------------------------------------
//| LEFT
//+-----------------------------------------------------------------------------

$left = <<<'NOWDOC'
<hr>
<p>title: Markdown (Github Flavored)
category: Other</p>
<hr>
<p>Markdown est un langage de balisage (<em>markup langage</em> en anglais).
Un peu à la manière du HTML, il permet de formatter le texte via un système d&#39;annotations.
Le Markdown se veut facile à lire, y compris dans sa version texte, et il est donc plus instinctif à apprendre.
Il est souvent supporté par les systèmes de commentaires, chats et forums.</p>
<p><ins>Exemple de syntaxe Markdown</ins> :</p>
<pre><code># Titre

Du *texte en italique*, **en gras**.

* Une liste
* d&#39;items

``` js
function hello() {
alert(&quot;Hello&quot;);
}
```</code></pre>
<p>En général, un fichier Markdown a l&#39;extension <code>.md</code> ou <code>.markdown</code>.</p>
<p>Il existe différents parser Markdown, chacun apportant quelques nuances et fonctionnalités différentes.
Par exemple, certains parser acceptent les tags HTML, implémentent les tables, les attributs de bloc (classe, id, etc).</p>
<p>La syntaxe décrite ci-dessous est la syntaxe supportée par Github, dite <em>Github Flavored Markdown</em> (GFM).
Le parser utilisé par Github est <a href="https://github.com/gjtorikian/commonmarker">CommonMark</a>.
<a href="https://github.github.com/gfm/">Voir les specs</a>. <a href="https://gist.github.com/a-mt/543bd5c34923c3f18f67d3af42ec570c">Voir fichier test</a></p>
<hr>
<h2 id="titre">Titre</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td># h1 Heading</td><td><h1>h1 Heading</h1></td></tr>
<tr><td>## h2 Heading</td><td><h2>h2 Heading</h2></td></tr>
<tr><td>### h3 Heading</td><td><h3>h3 Heading</h3></td></tr>
<tr><td>#### h4 Heading</td><td><h4>h4 Heading</h4></td></tr>
<tr><td>##### h5 Heading</td><td><h5>h5 Heading</h5></td></tr>
<tr><td>###### h6 Heading</td><td><h6>h6 Heading</h6></td></tr>
<tr><td>This is an H1<br>=============</td><td><h1>This is an H1</h1></td></tr>
<tr><td>This is an H2<br>-------------</td><td><h2>This is an H2</h2></td></tr>
</tbody>
</table>

<hr>
<h2 id="texte">Texte</h2>
<p>La syntaxe Markdown de formattage de texte n&#39;est pas interprétée à l&#39;intérieur d&#39;un <code>pre</code>.
Pour formatter du texte à l&#39;intérieur d&#39;un <code>pre</code>, utiliser des balises HTML.</p>
<h3 id="en-dehors-dun-pre">En dehors d&#39;un <code>pre</code></h3>
<table style="table-layout:fixed">
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td>**This is bold text**</td><td><strong>This is bold text</strong></td></tr>
<tr><td>__This is bold text__</td><td><strong>This is bold text</strong></td></tr>
<tr><td>*This is italic text*</td><td><em>This is italic text</em></td></tr>
<tr><td>_This is italic text_</td><td><em>This is italic text</em></td></tr>
<tr><td>~~Strikethrough~~</td><td><del>Strikethrough</del></td></tr>
<tr><td>\*Literal asterisks\*</td><td>*Literal asterisks*</td></tr>
</tbody>
</table>

<h3 id="dedans-ou-en-dehors-dun-pre">Dedans ou en dehors d&#39;un <code>pre</code></h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td><strong>This is bold text</del></td><td><strong>This is bold text</strong></td></tr>
<tr><td><em>This is italic text</em></td><td><em>This is italic text</em></td></tr>
<tr><td><del>Strikethrough</del></td><td><del>Strikethrough</del></td></tr>
<tr><td><s>Strikethrough</s></td><td><s>Strikethrough</s></td></tr>
<tr><td><ins>Underline</ins></td><td><ins>Underline</ins></td></tr>
<tr><td>Indice <sub>sub</sub></td><td>Indice <sub>sub</sub></td></tr>
<tr><td>Exposant <sup>sup</sup></td><td>Exposant <sup>sup</sup></td></tr>
<tr><td>©</td><td>©</td></tr>
<tr><td>➤</td><td>➤</td></tr>
</tbody>
</table>

<hr>
<h2 id="retours-à-la-ligne">Retours à la ligne</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td><p>Les retours à la ligne en fin de ligne<br>sont ignorés</p></td>
<td><p>Les retours à la ligne en fin de ligne sont ignorés</p></td>
</tr>
<tr>
<td><p>Ajouter deux espaces à la fin  <br>Pour préserver le retour à la ligne</p></td>
<td><p>Ajouter deux espaces à la fin<br>Pour préserver le retour à la ligne</p></td>
</tr>
<tr>
<td><p>Ou séparer les lignes<br><br>D'une ligne vide</p></td>
<td><p>Ou séparer les lignes</p><p>D'une ligne vide</p></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="code">Code</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<!-- En ligne -->
<tr>
<td>Inline <code>code</code></td>
<td>Inline <code>code</code></td>
</tr>
<tr>
<td>Inline `code`</td>
<td>Inline <code>code</code></td>
</tr>
<!-- En bloc -->
<tr>
<td>    Non interpreted <i>block code (4 spaces)</i><br></td>
<td><pre><code>Non interpreted <i>block code</i></code></pre></td>
</tr>
<tr>
<td><pre><br>Interpreted <i>block code</i><br></pre></td>
<td><pre>Interpreted <i>block code</i></pre></td>
</tr>
<tr>
<td>```<br>Non interpreted <i>block code</i><br>```</td>
<td><pre><code>Non interpreted <i>block code</i></code></pre></td>
</tr>
<!-- Avec coloration syntaxtique -->
<tr>
<td>
<pre lang="diff"><br>
diff --git a/filea.extension b/fileb.extension<br>
index d28nd309d..b3nu834uj 111111<br>
--- a/filea.extension<br>
+++ b/fileb.extension<br>
@@ -1,6 +1,6 @@<br>
-oldLine<br>
+newLine<br>
</pre><br>
</td>
<td><pre lang="diff">diff --git a/filea.extension b/fileb.extension
index d28nd309d..b3nu834uj 111111
--- a/filea.extension
+++ b/fileb.extension
@@ -1,6 +1,6 @@
-oldLine
+newLine</pre>
</td>
</tr>
<tr>
<td>``` js<br>
var foo = function (bar) {<br>
return bar++;<br>
};<br>
```<br>
</td>
<td><pre lang="js">var foo = function (bar) {
return bar++;
};</pre>
</td>
</tr>
<tr>
<td><kbd>Ctrl</kbd> + <kbd>S</kbd></td>
<td><kbd>Ctrl</kbd> + <kbd>S</kbd></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="blockquote">Blockquote</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>> Blockquote<br>
Still blockquote  <br>
Again<br>
>> sub-blockquote<br>
> > > sub-sub blockquote.
</td>
<td>
<blockquote>
<p>Blockquote Still blockquote<br>Again</p>
<blockquote>
<p>sub-blockquote</p>
<blockquote>
<p>sub-sub blockquote</p>
</blockquote>
</blockquote>
</blockquote>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="image">Image</h2>
<p>Syntaxe interprétée à l&#39;intérieur d&#39;un <code>pre</code></p>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">![Alt](h​ttps://placehold.it/50x50)</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt"></td>
</tr>
<tr><td align="">![Alt](h​ttps://placehold.it/50x50 &quot;title&quot;)</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt" title="title"></td>
</tr>
<tr><td align="">![Alt][id_img]<br>[id_img]: h​ttps://placehold.it/50x50</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt"></td>
</tr>
</tbody>
</table>
<hr>
<h2 id="lien">Lien</h2>
<p>Syntaxe interprétée à l&#39;intérieur d&#39;un <code>pre</code></p>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">h​ttp://google.com</td>
<td align=""><a href="http://google.com">http://google.com</a></td>
</tr>
<tr><td align="">[Text](h​ttp://google.com)</td>
<td align=""><a href="http://google.com">Text</a></td>
</tr>
<tr><td align="">[Text](h​ttp://google.com &quot;title&quot;)</td>
<td align=""><a href="http://google.com" title="title">Text</a></td>
</tr>
<tr><td align="">[Text][id_link]<br>[id_link]: h​ttp://google.com &quot;optional title&quot;</td>
<td align=""><a href="http://google.com" title="optional title&quot;">Text</a></td>
</tr>
<tr><td align="">h​ttp://google.com</td>
<td align="">h​ttp://google.com</td>
</tr>
</tbody>
</table>
<hr>
<h2 id="liste">Liste</h2>
<h3 id="À-puce">À puce</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
* Item 1<br>
With content<br>
* Item 2 (2 spaces)  <br>
With content<br>
+ Item 1<br>
+ Item 2<br>
- Item 1<br>
- Item 2<br><br>
</td>
<td>
<ul>
<li>Item 1
With content</li>
<li>Item 2 (2 spaces)<br>
With content</li>
</ul>
<ul>
<li>Item 1</li>
<li>Item 2</li>
</ul>
<ul>
<li>Item 1</li>
<li>Item 2</li>
</ul>
</td>
</tr>
</tbody>
</table>

<h3 id="Énumérée">Énumérée</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
1. Item 1<br>
2. Item 2<br>
3. Item 3<br>
   * Item 3a<br>
   * Item 3b<br>
1. Item 4<br>
   The number doesn't really matter<br>
1. Item 5<br>
2. Item 6<br>
2. Item 7<br>
</td>
<td>
<ol>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3
<ul>
<li>Item 3a</li>
<li>Item 3b</li>
</ul>
</li>
<li>Item 4
The number doesn't really matter</li>
<li>Item 5</li>
<li>Item 6</li>
<li>Item 7</li>
</ol>
</td>
</tr>
</tbody>
</table>

<h3 id="de-todos">De todos</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
- [x] This is a complete item<br>
- [ ] This is an incomplete item<br>
<br>
</td>
<td>

<ul>
<li class="task checked"><input checked="" disabled="" type="checkbox"> This is a complete item</li>
<li class="task"><input disabled="" type="checkbox"> This is an incomplete item</li>
</ul>
</td></tr></tbody></table>

<h3 id="séparer-deux-listes">Séparer deux listes</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
1. Item 1<br>
<br>
1. Item 2<br>
<br>
<!-- --><br>
<br>
1. Une autre liste !<br>
<br>
</td>
<td>
<ol>
<li>
<p>Item 1</p>
</li>
<li>
<p>Item 2</p>
</li>
</ol>
<!-- -->
<ol>
<li>Une autre liste !</li>
</ol>
<br><br><br>
</td>
</tr>
</tbody>
</table>

<h3 id="texte-autour">Texte autour</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
du texte avant<br>
1. Item 1<br>
du texte après<br>
</td>
<td>
<p>du texte avant</p>
<ol>
<li>Item 1
du texte après</li>
</ol>
</td>
</tr>
<tr>
<td>
du texte avant<br>
* Item 1<br>
du texte après<br>
</td>
<td>
<p>du texte avant</p>
<ul>
<li>Item 1
du texte après</li>
</ul>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="délimiteur-horizontal-rule">Délimiteur (horizontal rule)</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>___</td>
<td><hr></td>
</tr>
<tr>
<td>---</td>
<td><hr></td>
</tr>
<tr>
<td>***</td>
<td><hr></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="table">Table</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td><pre>
| Default | Align left | Align center | Align right |
| --- | :--- | :---: | ---: |
| A | B | C | E |
| F \| G | H | I | J |
</pre></td>
<td>
<table>
<thead>
<tr>
<th>Default is left</th>
<th align="left">Left-aligned</th>
<th align="center">Center-aligned</th>
<th align="right">Right-aligned</th>
</tr>
</thead>
<tbody>
<tr>
<td>A</td>
<td align="left">B</td>
<td align="center">C</td>
<td align="right">E</td>
</tr>
<tr>
<td>F | G</td>
<td align="left">H</td>
<td align="center">I</td>
<td align="right">J</td>
</tr></tbody></table>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="emojis">Emojis</h2>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">:​sparkles: :​camel: :​boom:</td>
<td align="">:sparkles: :camel: :boom:</td>
</tr>
</tbody>
</table>
<p>Liste complète des emojis : <a href="http://www.emoji-cheat-sheet.com/">http://www.emoji-cheat-sheet.com/</a> (<a href="https://github.com/WebpageFX/emoji-cheat-sheet.com/tree/master/public/graphics/emojis">Github</a>)</p>
<hr>
<h2 id="bloc-details">Bloc Details</h2>
<pre><code>&lt;details&gt;
&lt;summary&gt;Click here to expand.&lt;/summary&gt;
&lt;br&gt;
The content of the div
&lt;/details&gt;</code></pre>
<details>
<summary>Click here to expand.</summary>
<br>
The content of the div
</details>
NOWDOC;

//+-----------------------------------------------------------------------------
//| RIGHT
//+-----------------------------------------------------------------------------

$right = <<<'NOWDOC'
<hr>
<p>title: Markdown (Github Flavored)
category: Other</p>
<hr>
<p>Markdowns est un langage de balisage (<em>markup langage</em> en anglais).
Un peu à la manière du HTML, il permet de formatter le texte via un système d&#39;annotations.
Le Markdown se veut facile à lire, y compris dans sa version texte, et il est donc plus instinctif à apprendre.
Il est souvent supporté par les systèmes de commentaires, chats et forums.</p>
<p><ins>Exemgple de syntaxe Markdown</ins> :</p>
<pre><code># Titre

Du *texte en italique*, **en gras**.

* Une liste
* d&#39;items

``` js
function hello() {
alert(&quot;Hello&quot;);
}
```</code></pre>
<p>Il existe différents <a href="https://gisthub.com/github/markup#markups">parser Markdown</a>, chacun apportant quelques nuances et fonctionnalités différentes.
Par exemple, certains parser acceptent les tags HTML, implémentent les tables, les attributs de bloc (classe, id, etc).</p>
<p>La syntaxe décrite ci-dessous est la syntaxe supportée par Github, dite <em>Github Flavored Markdown</em> (GFM).
Le parser utilisé par Github est <a href="https://github.com/gjtorikian/commonmarker">CommonMark</a>.
<a href="https://github.github.com/gfm/">Voir les specs</a>. <a href="https://gist.github.com/a-mt/543bd5c34923c3f18f67d3af42ec570c">Voir fichier test</a></p>
<p>ABC</p>
<hr>
<h2 id="titre">Titre</h2>
<table>
<thead><tr>
<th>            Asvant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td># h1 Hfeading</td><td><h1>h1 Hfeading</h1></td></tr>
<tr><td>## h2 Heading</td><td><h2>h2 Heading</h2></td></tr>
<tr><td>### h3 Heading</td><td><h3>h3 Heading</h3></td></tr>
<tr><td>#### h4 Heading</td><td><h4>h4 Heading</h4></td></tr>
<tr><td>##### h5 Heading</td><td><h5>h5 Heading</h5></td></tr>
<tr><td>###### h6 Heading</td><td><h6>h6 Heading</h6></td></tr>
<tr><td>This is an H1<br>=============</td><td><h1>This is an H1</h1></td></tr>
<tr><td>This is an H2<br>-------------</td><td><h2>This is an H2</h2></td></tr>
</tbody>
</table>

<hr>
<h2 id="texte">Texte</h2>
<p>La syntaxe Markdown de formattage de texte n&#39;est pas interprétée à l&#39;intérieur d&#39;un <code>pre</code>.
Pour formatter du texte à l&#39;intérieur d&#39;un <code>pre</code>, utiliser des balises HTML.</p>
<h3 id="en-dehors-dun-pre">En dehors d&#39;un <code>pre</code></h3>
<table style="table-layout:fixed">
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td>**This is bold text**</td><td><strong>This is bold text</strong></td></tr>
<tr><td>__This is bold text__</td><td><strong>This is bold text</strong></td></tr>
<tr><td>*This is italic text*</td><td><em>This is italic text</em></td></tr>
<tr><td>_This is italic text_</td><td><em>This is italic text</em></td></tr>
<tr><td>~~Strikethrough~~</td><td><del>Strikethrough</del></td></tr>
<tr><td>\*Literal asterisks\*</td><td>*Literal asterisks*</td></tr>
</tbody>
</table>

<h3 id="dedans-ou-en-dehors-dun-pre">Dedans ou en dehors d&#39;un <code>pre</code></h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr><td><strong>This is bold text</del></td><td><strong>This is bold text</strong></td></tr>
<tr><td><em>This is italic text</em></td><td><em>This is italic text</em></td></tr>
<tr><td><del>Strikethrough</del></td><td><del>Strikethrough</del></td></tr>
<tr><td><s>Strikethrough</s></td><td><s>Strikethrough</s></td></tr>
<tr><td><ins>Underline</ins></td><td><ins>Underline</ins></td></tr>
<tr><td>Indice <sub>sub</sub></td><td>Indice <sub>sub</sub></td></tr>
<tr><td>Exposant <sup>sup</sup></td><td>Exposant <sup>sup</sup></td></tr>
<tr><td>&copy;</td><td>©</td></tr>
<tr><td>&#10148;</td><td>➤</td></tr>
</tbody>
</table>

<hr>
<h2 id="retours-à-la-ligne">Retours à la ligne</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td><p>Les retours à la ligne en fin de ligne<br>sont ignorés</p></td>
<td><p>Les retours à la ligne en fin de ligne sont ignorés</p></td>
</tr>
<tr>
<td><p>Ajouter deux espaces à la fin <br>Pour préserver le retour à la ligne</p></td>
<td><p>Ajouter deux espaces à la fin<br>Pour préserver le retour à la ligne</p></td>
</tr>
<tr>
<td><p>Ou séparer les lignes<br><br>D'une ligne vide</p></td>
<td><p>Ou séparer les lignes</p><p>D'une ligne vide</p></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="code">Code</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<!-- En ligne -->
<tr>
<td>Inline <code>code</code></td>
<td>Inline <code>code</code></td>
</tr>
<tr>
<td>Inline `code`</td>
<td>Inline <code>code</code></td>
</tr>
<!-- En bloc -->
<tr>
<td> Non interpreted <i>block code (4 spaces)</i><br></td>
<td><pre><code>Non interpreted <i>block code</i></code></pre></td>
</tr>
<tr>
<td><pre><br>Interpreted <i>block code</i><br></pre></td>
<td><pre>Interpreted <i>block code</i></pre></td>
</tr>
<tr>
<td>```<br>Non interpreted <i>block code</i><br>```</td>
<td><pre><code>Non interpreted <i>block code</i></code></pre></td>
</tr>
<!-- Avec coloration syntaxtique -->
<tr>
<td>
<pre lang="diff"><br>
diff --git a/filea.extension b/fileb.extension<br>
index d28nd309d..b3nu834uj 111111<br>
--- a/filea.extension<br>
+++ b/fileb.extension<br>
@@ -1,6 +1,6 @@<br>
-oldLine<br>
+newLine<br>
</pre><br>
</td>
<td><pre lang="diff">diff --git a/filea.extension b/fileb.extension
index d28nd309d..b3nu834uj 111111
--- a/filea.extension
+++ b/fileb.extension
@@ -1,6 +1,6 @@
-oldLine
+newLine</pre>
</td>
</tr>
<tr>
<td>``` js<br>
var foo = function (bar) {<br>
return bar++;<br>
};<br>
```<br>
</td>
<td><pre lang="js">var foo = function (bar) {
return bar++;
};</pre>
</td>
</tr>
<tr>
<td><kbd>Ctrl</kbd> + <kbd>S</kbd></td>
<td><kbd>Ctrl</kbd> + <kbd>S</kbd></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="blockquote">Blockquote</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>> Blockquote<br>
Still blockquote <br>
Again<br>
>> sub-blockquote<br>
> > > sub-sub blockquote.
</td>
<td>
<blockquote>
<p>Blockquote Still blockquote<br>Again</p>
<blockquote>
<p>sub-blockquote</p>
<blockquote>
<p>sub-sub blockquote</p>
</blockquote>
</blockquote>
</blockquote>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="image">Image</h2>
<p>Syntaxe interprétée à l&#39;intérieur d&#39;un <code>pre</code></p>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">![Alt](h​ttps://placehold.it/50x50)</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt"></td>
</tr>
<tr><td align="">![Alt](h​ttps://placehold.it/50x50 &quot;title&quot;)</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt" title="title"></td>
</tr>
<tr><td align="">![Alt][id_img]<br>[id_img]: h​ttps://placehold.it/50x50</td>
<td align=""><img src="https://placehold.it/50x50" alt="Alt"></td>
</tr>
</tbody>
</table>
<hr>
<h2 id="lien">Lien</h2>
<p>Syntaxe interprétée à l&#39;intérieur d&#39;un <code>pre</code></p>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">h​ttp://google.com</td>
<td align=""><a href="http://google.com">http://google.com</a></td>
</tr>
<tr><td align="">[Text](h​ttp://google.com)</td>
<td align=""><a href="http://google.com">Text</a></td>
</tr>
<tr><td align="">[Text](h​ttp://google.com &quot;title&quot;)</td>
<td align=""><a href="http://google.com" title="title">Text</a></td>
</tr>
<tr><td align="">[Text][id_link]<br>[id_link]: h​ttp://google.com &quot;optional title&quot;</td>
<td align=""><a href="http://google.com" title="optional title&quot;">Text</a></td>
</tr>
<tr><td align="">h&#8203;ttp://google.com</td>
<td align="">h​ttp://google.com</td>
</tr>
</tbody>
</table>
<hr>
<h2 id="liste">Liste</h2>
<h3 id="À-puce">À puce</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
* Item 1<br>
With content<br>
* Item 2 (2 spaces) <br>
With content<br>
+ Item 1<br>
+ Item 2<br>
- Item 1<br>
- Item 2<br><br>
</td>
<td>
<ul>
<li>Item 1
With content</li>
<li>Item 2 (2 spaces)<br>
With content</li>
</ul>
<ul>
<li>Item 1</li>
<li>Item 2</li>
</ul>
<ul>
<li>Item 1</li>
<li>Item 2</li>
</ul>
</td>
</tr>
</tbody>
</table>

<h3 id="Énumérée">Énumérée</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
1. Item 1<br>
2. Item 2<br>
3. Item 3<br>
* Item 3a<br>
* Item 3b<br>
1. Item 4<br>
The number doesn't really matter<br>
1. Item 5<br>
2. Item 6<br>
2. Item 7<br>
</td>
<td>
<ol>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3
<ul>
<li>Item 3a</li>
<li>Item 3b</li>
</ul>
</li>
<li>Item 4
The number doesn't really matter</li>
<li>Item 5</li>
<li>Item 6</li>
<li>Item 7</li>
</ol>
</td>
</tr>
</tbody>
</table>

<h3 id="de-todos">De todos</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
- [x] This is a complete item<br>
- [ ] This is an incomplete item<br>
<br>
</td>
<td>

<ul>
<li class="task checked"><input checked="" disabled="" type="checkbox"> This is a complete item</li>
<li class="task"><input disabled="" type="checkbox"> This is an incomplete item</li>
</ul>
</td></tr></tbody></table>

<h3 id="séparer-deux-listes">Séparer deux listes</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
1. Item 1<br>
<br>
1. Item 2<br>
<br>
<!-- --><br>
<br>
1. Une autre liste !<br>
<br>
</td>
<td>
<ol>
<li>
<p>Item 1</p>
</li>
<li>
<p>Item 2</p>
</li>
</ol>
<!-- -->
<ol>
<li>Une autre liste !</li>
</ol>
<br><br><br>
</td>
</tr>
</tbody>
</table>

<h3 id="texte-autour">Texte autour</h3>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>
du texte avant<br>
1. Item 1<br>
du texte après<br>
</td>
<td>
<p>du texte avant</p>
<ol>
<li>Item 1
du texte après</li>
</ol>
</td>
</tr>
<tr>
<td>
du texte avant<br>
* Item 1<br>
du texte après<br>
</td>
<td>
<p>du texte avant</p>
<ul>
<li>Item 1
du texte après</li>
</ul>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="délimiteur-horizontal-rule">Délimiteur (horizontal rule)</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td>___</td>
<td><hr></td>
</tr>
<tr>
<td>---</td>
<td><hr></td>
</tr>
<tr>
<td>***</td>
<td><hr></td>
</tr>
</tbody>
</table>

<hr>
<h2 id="table">Table</h2>
<table>
<thead><tr>
<th>            Avant           </th>
<th>            Après           </th>
</tr></thead>
<tbody>
<tr>
<td><pre>
| Default | Align left | Align center | Align right |
| --- | :--- | :---: | ---: |
| A | B | C | E |
| F \| G | H | I | J |
</pre></td>
<td>
<table>
<thead>
<tr>
<th>Default is left</th>
<th align="left">Left-aligned</th>
<th align="center">Center-aligned</th>
<th align="right">Right-aligned</th>
</tr>
</thead>
<tbody>
<tr>
<td>A</td>
<td align="left">B</td>
<td align="center">C</td>
<td align="right">E</td>
</tr>
<tr>
<td>F | G</td>
<td align="left">H</td>
<td align="center">I</td>
<td align="right">J</td>
</tr></tbody></table>
</td>
</tr>
</tbody>
</table>

<hr>
<h2 id="emojis">Emojis</h2>
<table>
<thead>
<tr><th align="">            Avant           </th>
<th align="">            Après           </th>
</tr>
</thead>
<tbody>
<tr><td align="">:​sparkles: :​camel: :​boom:</td>
<td align="">:sparkles: :camel: :boom:</td>
</tr>
</tbody>
</table>
<p>Liste complète des emojis : <a href="http://www.emoji-cheat-sheet.com/">http://www.emoji-cheat-sheet.com/</a> (<a href="https://github.com/WebpageFX/emoji-cheat-sheet.com/tree/master/public/graphics/emojis">Github</a>)</p>
<hr>
<h2 id="bloc-details">Bloc Details</h2>
<pre><code>&lt;details&gt;
&lt;summary&gt;Click here to expand.&lt;/summary&gt;
&lt;br&gt;
The content of the div
&lt;/details&gt;</code></pre>
<details>
<summary>tClick here to expand.</summary>
<br>
The content of the div
</details>
NOWDOC;

//+-----------------------------------------------------------------------------
//| DISPLAY
//+-----------------------------------------------------------------------------
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Diff Match Patch</title>
    <link rel="stylesheet" href="style.css">
    <script>
      function diff(e) {
        e.preventDefault();

        var data = new FormData();
        data.append("leftTxt", this.elements.left.value);
        data.append("rightTxt", this.elements.right.value);

        window.fetch(window.location.pathname, {
            method: "POST",
            body: data
        })
        .then(res => res.text())
        .then(data => {
            document.getElementById('output').innerHTML = data;
        });
      } 
    </script>
  </head>
  <body>

    <form id="form" onSubmit="return diff.call(this, event)">
      <div id="input">
        <textarea name="left" rows="10"><?= $left ?></textarea>
        <textarea name="right" rows="10"><?= $right ?></textarea>
      </div>
      <button type="submit">Compute Diff</button>
    </form>
    <div id="output" class="markdown-body"></div>

  </body>
</html>