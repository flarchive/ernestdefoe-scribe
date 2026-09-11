const config = require('flarum-webpack-config');

module.exports = () => {
  const base = config();

  /*
   * 🚨 Keep Babel away from node_modules.
   *
   * Flarum's shared config runs babel-loader over everything, including
   * dependencies. TipTap 3 ships its dist with JSX already compiled against its
   * own automatic runtime (`@tiptap/core/jsx-runtime`), and Flarum's Babel
   * setup pins the classic runtime with an `m` pragma for Mithril. Handing
   * TipTap's dist to that preset fails outright with "importSource cannot be
   * set when runtime is classic" on every single @tiptap package.
   *
   * TipTap already publishes browser-ready ES modules, so there is nothing for
   * Babel to do here beyond breaking it.
   */
  for (const rule of base.module.rules) {
    if (String(rule.test) === String(/\.[jt]sx?$/)) {
      rule.exclude = /node_modules/;
    }
  }

  return base;
};
