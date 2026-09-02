import type { Editor } from '@tiptap/core';

export interface ScribeButton {
  key: string;
  icon: string;
  /** Translation key suffix under ernestdefoe-scribe.forum.composer */
  label: string;
  /** Whether the mark/node is active at the cursor, for the pressed state. */
  active?: (e: Editor) => boolean;
  /** Whether the command can run right now, for the disabled state. */
  enabled?: (e: Editor) => boolean;
  /** Absent when the button opens a form instead of acting immediately. */
  run?: (e: Editor) => void;
  /** Opens a form rather than toggling immediately. */
  prompt?: 'link' | 'image' | 'color';
  /**
   * A short suffix drawn on the icon.
   *
   * 🚨 Heading and Subheading share Font Awesome's only heading glyph, so
   * side by side in the toolbar they were two identical buttons doing
   * different things — the tooltip was the only way to tell them apart.
   */
  badge?: string;
}

/**
 * Every button Scribe can show. The AdminCP decides which of these are on and
 * in what order; nothing here assumes it is visible.
 *
 * 🚨 A button that is registered but never wired is worse than a missing one —
 * it looks like a feature and does nothing. Each entry carries its own `run`,
 * so adding a key to the registry is the same act as making it work.
 */
export const SCRIBE_BUTTONS: ScribeButton[] = [
  { key: 'bold', icon: 'fas fa-bold', label: 'bold',
    active: (e) => e.isActive('bold'), run: (e) => e.chain().focus().toggleBold().run() },
  { key: 'italic', icon: 'fas fa-italic', label: 'italic',
    active: (e) => e.isActive('italic'), run: (e) => e.chain().focus().toggleItalic().run() },
  { key: 'underline', icon: 'fas fa-underline', label: 'underline',
    active: (e) => e.isActive('underline'), run: (e) => e.chain().focus().toggleUnderline().run() },
  { key: 'strike', icon: 'fas fa-strikethrough', label: 'strike',
    active: (e) => e.isActive('strike'), run: (e) => e.chain().focus().toggleStrike().run() },
  { key: 'code', icon: 'fas fa-code', label: 'code',
    active: (e) => e.isActive('code'), run: (e) => e.chain().focus().toggleCode().run() },
  { key: 'color', icon: 'fas fa-palette', label: 'text_color', prompt: 'color',
    active: (e) => e.isActive('scribeColor') },
  { key: 'highlight', icon: 'fas fa-highlighter', label: 'highlight',
    active: (e) => e.isActive('highlight'), run: (e) => e.chain().focus().toggleHighlight().run() },
  { key: 'heading2', icon: 'fas fa-heading', label: 'heading', badge: '2',
    active: (e) => e.isActive('heading', { level: 2 }),
    run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run() },
  { key: 'heading3', icon: 'fas fa-heading', label: 'subheading', badge: '3',
    active: (e) => e.isActive('heading', { level: 3 }),
    run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run() },
  { key: 'bulletList', icon: 'fas fa-list-ul', label: 'bullet_list',
    active: (e) => e.isActive('bulletList'), run: (e) => e.chain().focus().toggleBulletList().run() },
  { key: 'orderedList', icon: 'fas fa-list-ol', label: 'ordered_list',
    active: (e) => e.isActive('orderedList'), run: (e) => e.chain().focus().toggleOrderedList().run() },
  { key: 'blockquote', icon: 'fas fa-quote-left', label: 'quote',
    active: (e) => e.isActive('blockquote'), run: (e) => e.chain().focus().toggleBlockquote().run() },
  { key: 'codeBlock', icon: 'fas fa-file-code', label: 'code_block',
    active: (e) => e.isActive('codeBlock'), run: (e) => e.chain().focus().toggleCodeBlock().run() },
  { key: 'link', icon: 'fas fa-link', label: 'link', prompt: 'link',
    active: (e) => e.isActive('link') },
  { key: 'image', icon: 'fas fa-image', label: 'image', prompt: 'image' },
  { key: 'table', icon: 'fas fa-table', label: 'table',
    run: (e) => e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
  { key: 'horizontalRule', icon: 'fas fa-minus', label: 'horizontal_rule',
    run: (e) => e.chain().focus().setHorizontalRule().run() },
  { key: 'undo', icon: 'fas fa-undo', label: 'undo',
    enabled: (e) => e.can().undo(), run: (e) => e.chain().focus().undo().run() },
  { key: 'redo', icon: 'fas fa-redo', label: 'redo',
    enabled: (e) => e.can().redo(), run: (e) => e.chain().focus().redo().run() },
];

/** Shown when the admin has never touched the setting. */
export const DEFAULT_TOOLBAR = [
  'bold', 'italic', 'strike', 'code',
  'heading2', 'bulletList', 'orderedList', 'blockquote', 'codeBlock',
  'link', 'image',
];

export function buttonsFor(keys: string[]): ScribeButton[] {
  const byKey = new Map(SCRIBE_BUTTONS.map((b) => [b.key, b]));
  // Unknown keys are dropped rather than rendered blank — a setting saved by an
  // older version must not leave a dead control in the toolbar.
  return keys.map((k) => byKey.get(k)).filter((b): b is ScribeButton => !!b);
}
