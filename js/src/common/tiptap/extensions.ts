import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Strike from '@tiptap/extension-strike';
import Underline from '@tiptap/extension-underline';
import Code from '@tiptap/extension-code';
import CodeBlock from '@tiptap/extension-code-block';
import Heading from '@tiptap/extension-heading';
import BulletList from '@tiptap/extension-bullet-list';
import OrderedList from '@tiptap/extension-ordered-list';
import ListItem from '@tiptap/extension-list-item';
import Blockquote from '@tiptap/extension-blockquote';
import HardBreak from '@tiptap/extension-hard-break';
import HorizontalRule from '@tiptap/extension-horizontal-rule';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Highlight from '@tiptap/extension-highlight';
import History from '@tiptap/extension-history';
import Dropcursor from '@tiptap/extension-dropcursor';
import Gapcursor from '@tiptap/extension-gapcursor';
import Placeholder from '@tiptap/extension-placeholder';
import { TextStyle } from '@tiptap/extension-text-style';
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table';
import { Mark, mergeAttributes } from '@tiptap/core';

/**
 * A colour is stored in `data-color` and filtered server-side by s9e's #color.
 *
 * 🚨 Deliberately NOT TipTap's own Color extension, which writes
 * `style="color: …"`. A style string has to be re-parsed and sanitised on the
 * server before it can be trusted, and "red;background:url(…)" looks like an
 * ordinary colour right up until it isn't. A bare attribute has one meaning and
 * one filter.
 */
export const ScribeColor = Mark.create({
  name: 'scribeColor',
  addAttributes() {
    return {
      color: {
        default: null,
        parseHTML: (el: HTMLElement) => el.getAttribute('data-color'),
        renderHTML: (attrs: Record<string, any>) =>
          attrs.color ? { 'data-color': attrs.color } : {},
      },
    };
  },
  parseHTML() {
    return [{ tag: 'span[data-color]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes), 0];
  },
});

export function buildExtensions(placeholder: string) {
  return [
    Document,
    Paragraph,
    Text,
    Bold,
    Italic,
    Strike,
    Underline,
    Code,
    CodeBlock,
    // Only the levels the server has templates for and a forum post actually
    // needs — h1 in a reply is shouting, and h4-h6 are indistinguishable.
    Heading.configure({ levels: [2, 3] }),
    BulletList,
    /*
     * 🚨 `data-type` is not decoration. Both <ul> and <ol> alias to the single
     * LIST tag server-side, and the template picks <ul> vs <ol> purely on
     * whether @type is present — so an ordered list that omits this silently
     * renders as a bulleted one.
     */
    OrderedList.extend({
      renderHTML({ HTMLAttributes }) {
        return ['ol', mergeAttributes(HTMLAttributes, { 'data-type': 'decimal' }), 0];
      },
    }),
    ListItem,
    Blockquote,
    HardBreak,
    HorizontalRule,
    Link.configure({ openOnClick: false, autolink: true }),
    Image,
    Highlight,
    History,
    Dropcursor,
    Gapcursor,
    TextStyle,
    ScribeColor,
    Table.configure({ resizable: true }),
    TableRow,
    TableHeader,
    TableCell,
    Placeholder.configure({ placeholder }),
  ];
}
