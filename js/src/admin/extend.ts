import Extend from 'flarum/common/extenders';
import ToolbarBuilder from './components/ToolbarBuilder';

declare const m: any;

const SETTING = 'ernestdefoe-scribe.toolbar';

export default [
  new Extend.Admin().customSetting(function (this: any) {
    // `this` is the ExtensionPage, so the builder writes into the same stream
    // as every other setting and the page's own Save button persists it.
    return m(ToolbarBuilder, { setting: this.setting(SETTING) });
  }, 10),
];
