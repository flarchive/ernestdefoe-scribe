/**
 * The Markdown-and-BBCode translation other extensions insert through, tested.
 *
 * 🚨 This one function decides what happens to everything another extension
 * hands the composer — a quoted post, a finished upload — and it is pure
 * string handling, which is where silent wrongness lives. It had a customer-
 * reported bug (FoF Upload's image-preview tag arriving as raw text) and the
 * fix for it had a bug of its own that only a test caught: escapeHtml runs
 * first, so quoted attribute values arrive as `&quot;…&quot;` and matched
 * nothing.
 *
 * 🚨 Deliberately dependency-free. Scribe has no test framework and does not
 * need one for this: node runs it, tsc compiles the one file it covers, and a
 * suite with no install step is a suite that still runs in two years.
 *
 *     npm test
 */

import { toEditorContent } from '../../.test-build/legacyInsert.js';

let failed = 0;

function check(name, got, want) {
  const ok = typeof want === 'function' ? want(got) : got === want;
  console.log(`${ok ? '  ok  ' : '  FAIL'} ${name}`);
  if (!ok) {
    console.log(`        got:  ${JSON.stringify(got)}`);
    if (typeof want !== 'function') console.log(`        want: ${JSON.stringify(want)}`);
    failed++;
  }
}

// 🚨 The reported bug: FoF Upload's image-preview tag arrived as literal text.
const real = '[upl-image-preview url=https://dev.example.com/assets/files/2026-09-15/a1b2.png uuid=7f3c9e1a-2b4d thumbnail_url=https://dev.example.com/assets/files/2026-09-15/a1b2-thumb.png]';

check('the preview tag becomes an image',
  toEditorContent(real),
  (got) => got && got.includes('<img src="https://dev.example.com/assets/files/2026-09-15/a1b2.png"'));

check('the raw tag is gone',
  toEditorContent(real),
  (got) => got && !got.includes('upl-image-preview'));

// 🚨 The full-size url, never the thumbnail — substituting a downscaled copy
// would put a blurry image in a post whose author chose a sharp one.
check('the thumbnail is not substituted',
  toEditorContent(real),
  (got) => got && !got.includes('a1b2-thumb'));

// Attributes are unordered and the set differs by version.
check('attribute order does not matter',
  toEditorContent('[upl-image-preview uuid=abc thumbnail_url=https://x.test/t.png url=https://x.test/full.png]'),
  (got) => got && got.includes('src="https://x.test/full.png"'));

check('quoted attribute values work',
  toEditorContent('[upl-image-preview url="https://x.test/a b.png"]'),
  (got) => got && got.includes('src="https://x.test/a b.png"'));

// 🚨 A signed URL loses its query string if & is not un-escaped before reading.
check('a signed url keeps its query string',
  toEditorContent('[upl-image-preview url=https://x.test/f.png?sig=abc&exp=123]'),
  (got) => got && got.includes('sig=abc&amp;exp=123'));

// 🚨 The security rule the whole file rests on.
// 🚨 The security rule the whole file rests on: no <img> is produced for a
// scheme that is not http(s). The tag is left as literal text, which is both
// safe and visible — the poster can see something did not work.
check('javascript: makes no image',
  toEditorContent('[upl-image-preview url=javascript:alert(1)]'),
  (got) => got && !got.includes('<img'));

check('data: is refused',
  toEditorContent('[upl-image-preview url=data:text/html;base64,xxx]'),
  (got) => got && !got.includes('<img'));

// The formats that already worked must keep working.
check('markdown images still work',
  toEditorContent('![cat](https://x.test/cat.png)'),
  (got) => got && got.includes('<img src="https://x.test/cat.png" alt="cat">'));

check('quoting still works',
  toEditorContent('> quoted line'),
  (got) => got && got.startsWith('<blockquote>'));

// 🚨 Ordinary typing must still return null, or every keystroke becomes HTML.
check('plain text is left alone', toEditorContent('just some words'), null);
check('a bare bracket is left alone', toEditorContent('[not a tag]'), null);

// 🚨 The /g regexes are shared module state; a missed lastIndex reset makes the
// SECOND call behave differently from the first.
const twice = [toEditorContent(real), toEditorContent(real)];
check('repeated calls are identical', twice[0] === twice[1], true);

console.log(failed ? `\n${failed} failing` : '\nall passing');
process.exit(failed ? 1 : 0);
