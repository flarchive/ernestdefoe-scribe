/**
 * Translates the Markdown that other extensions insert into the composer.
 *
 * 🚨 This is not a Markdown parser and must never become one. Scribe exists so
 * that typing `**bold**` stays literal — the whole point. What this handles is
 * narrower and different: text that arrives through the editor driver's
 * insert* methods from OTHER extensions, which have every reason to still speak
 * Markdown because that is the contract Flarum's driver interface has always
 * had.
 *
 * Two of these matter enough that the editor is broken without them:
 *
 *   - Quoting a post. flarum/mentions builds `> quoted text` and hands it to
 *     insertAtCursor (utils/reply.js). Without translation, highlighting a post
 *     and hitting Reply drops a literal "> " into the composer.
 *   - FoF Upload inserts `![alt](url)` after an upload finishes.
 *
 * Anything not recognised is returned as null and inserted as plain text, which
 * is the correct outcome for ordinary typing.
 */

const IMAGE = /!\[([^\]]*)\]\(([^)\s]+)\)/g;
const LINK = /\[([^\]]+)\]\(([^)\s]+)\)/g;

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** Only http(s) and root-relative targets; never javascript: or data:. */
function safeUrl(url: string): string | null {
  const u = url.trim();
  if (u.startsWith('/') && !u.startsWith('//')) return u;
  return /^https?:\/\//i.test(u) ? u : null;
}

function inline(text: string): string {
  let out = escapeHtml(text);
  out = out.replace(IMAGE, (whole, alt, src) => {
    const safe = safeUrl(src);
    return safe ? `<img src="${escapeHtml(safe)}" alt="${escapeHtml(alt)}">` : whole;
  });
  out = out.replace(LINK, (whole, label, href) => {
    const safe = safeUrl(href);
    return safe ? `<a href="${escapeHtml(safe)}">${label}</a>` : whole;
  });
  return out;
}

/**
 * @returns HTML to insert, or null when the text holds nothing to translate.
 */
export function toEditorContent(text: string): string | null {
  const hasQuote = /^\s*>\s?/m.test(text);
  const hasMedia = IMAGE.test(text) || LINK.test(text);
  IMAGE.lastIndex = LINK.lastIndex = 0; // these are /g — reset before reuse
  if (!hasQuote && !hasMedia) return null;

  const blocks: string[] = [];
  let quoted: string[] = [];

  const flushQuote = () => {
    if (!quoted.length) return;
    blocks.push(`<blockquote>${quoted.map((l) => `<p>${inline(l)}</p>`).join('')}</blockquote>`);
    quoted = [];
  };

  for (const line of text.split('\n')) {
    const q = line.match(/^\s*>\s?(.*)$/);
    if (q) {
      quoted.push(q[1]);
      continue;
    }
    flushQuote();
    // A blank line between blocks is structure, not content — it must not
    // become an empty paragraph inside the quote.
    if (line.trim() !== '') blocks.push(`<p>${inline(line)}</p>`);
  }
  flushQuote();

  return blocks.join('');
}
