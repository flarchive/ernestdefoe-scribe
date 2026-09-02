import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import type { Editor } from '@tiptap/core';
import { buttonsFor, DEFAULT_TOOLBAR, type ScribeButton } from '../toolbarButtons';

export interface ScribeToolbarAttrs extends ComponentAttrs {
  editor?: Editor;
  /** The driver re-renders us; we are not in Flarum's redraw cycle. */
  onChange: () => void;
}

/** Colours offered by the swatch row. Deliberately few — a forum post is not a design tool. */
const SWATCHES = ['#d83e3e', '#e8590c', '#f08c00', '#2f9e44', '#1971c2', '#6741d9', '#868e96'];

export default class ScribeToolbar extends Component<ScribeToolbarAttrs> {
  prompt: 'link' | 'image' | 'color' | null = null;
  value = '';

  view() {
    const editor = this.attrs.editor;
    if (!editor) return null;

    const configured = app.forum.attribute<string[] | null>('scribeToolbar') ?? DEFAULT_TOOLBAR;

    return (
      <div className="Scribe-toolbarWrap">
        <div
          className="Scribe-toolbar"
          role="toolbar"
          aria-label={app.translator.trans('ernestdefoe-scribe.forum.composer.toolbar_label')}
        >
          {buttonsFor(configured).map((b) => this.button(b, editor))}
        </div>
        {this.prompt && this.promptRow(editor)}
      </div>
    );
  }

  button(b: ScribeButton, editor: Editor) {
    const label = app.translator.trans(`ernestdefoe-scribe.lib.buttons.${b.label}`);
    const active = b.active?.(editor) ?? false;

    return (
      <Tooltip text={label}>
        {Button.component({
          className:
            'Button Button--icon Button--link Scribe-toolbarButton' + (active ? ' is-active' : ''),
          icon: b.icon,
          // The pressed state is what tells a screen-reader user the cursor is
          // inside bold text, which sighted users read off the highlight.
          'aria-pressed': active ? 'true' : 'false',
          'aria-label': label,
          'data-badge': b.badge,
          disabled: b.enabled ? !b.enabled(editor) : false,
          onclick: () => {
            if (b.prompt) {
              this.openPrompt(b.prompt, editor);
            } else {
              b.run?.(editor);
            }
            this.attrs.onChange();
          },
        })}
      </Tooltip>
    );
  }

  openPrompt(kind: NonNullable<ScribeButton['prompt']>, editor: Editor) {
    // Reopening the same prompt closes it, so the button toggles.
    this.prompt = this.prompt === kind ? null : kind;
    this.value = kind === 'link' ? (editor.getAttributes('link').href ?? '') : '';
  }

  /**
   * 🚨 These three buttons open a form instead of toggling, and until this
   * existed they were wired to a no-op — present, labelled, styled, and doing
   * nothing. A control that looks like a feature and silently does nothing is
   * worse than one that is missing.
   */
  promptRow(editor: Editor) {
    const kind = this.prompt!;
    const close = () => {
      this.prompt = null;
      this.value = '';
      editor.commands.focus();
      this.attrs.onChange();
    };

    if (kind === 'color') {
      return (
        <div className="Scribe-prompt Scribe-prompt--color">
          {SWATCHES.map((c) => (
            <button
              type="button"
              className="Scribe-swatch"
              style={{ background: c }}
              aria-label={c}
              title={c}
              onclick={() => {
                editor.chain().focus().setMark('scribeColor', { color: c }).run();
                close();
              }}
            />
          ))}
          {Button.component(
            {
              className: 'Button Button--link Scribe-promptClear',
              onclick: () => {
                editor.chain().focus().unsetMark('scribeColor').run();
                close();
              },
            },
            app.translator.trans('ernestdefoe-scribe.forum.composer.clear_colour')
          )}
        </div>
      );
    }

    const placeholder = app.translator.trans(
      `ernestdefoe-scribe.forum.composer.${kind === 'link' ? 'link_placeholder' : 'image_placeholder'}`
    );

    const apply = () => {
      const url = this.value.trim();
      if (!url) return close();
      if (kind === 'link') {
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
      } else {
        editor.chain().focus().setImage({ src: url }).run();
      }
      close();
    };

    return (
      <div className="Scribe-prompt">
        <input
          className="FormControl Scribe-promptInput"
          type="url"
          placeholder={placeholder as string}
          value={this.value}
          oninput={(e: any) => {
            this.value = e.target.value;
          }}
          onkeydown={(e: KeyboardEvent) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              apply();
            } else if (e.key === 'Escape') {
              close();
            }
          }}
          oncreate={(v: any) => v.dom.focus()}
        />
        {Button.component(
          { className: 'Button Button--primary Scribe-promptApply', onclick: apply },
          app.translator.trans('ernestdefoe-scribe.forum.composer.apply')
        )}
        {kind === 'link' &&
          editor.isActive('link') &&
          Button.component(
            {
              className: 'Button Button--link',
              onclick: () => {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
                close();
              },
            },
            app.translator.trans('ernestdefoe-scribe.forum.composer.remove_link')
          )}
      </div>
    );
  }
}
