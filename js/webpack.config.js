const config = require('flarum-webpack-config');

const NS = 'ernestdefoe-scribe';

/*
 * 🚨 Lazy chunks need ids no other extension can have.
 *
 * Flarum's registry finds a chunk's URL by its id alone, and every extension's
 * chunks report to one shared loading global. Webpack's default ids are short
 * hashes of the chunk's path, so two extensions can share one: the wrong file
 * is fetched, or the chunk counts as already loaded, and the page dies with
 * "r[t] is not a function". So: ids prefixed with our extension id, and our
 * own chunk-loading global.
 */
class NamespacedChunkIds {
  apply(compiler) {
    compiler.hooks.compilation.tap('NamespacedChunkIds', (compilation) => {
      compilation.hooks.chunkIds.tap('NamespacedChunkIds', (chunks) => {
        for (const chunk of chunks) {
          if (chunk.id === null) {
            chunk.id = `${NS}:${chunk.name || chunk.debugId}`;
            chunk.ids = [chunk.id];
          }
        }
      });
    });
  }
}

const namespaced = (base) => {
  base.optimization = { ...base.optimization, chunkIds: false };
  base.output = { ...base.output, chunkLoadingGlobal: `webpackChunk_${NS.replace(/-/g, '_')}` };
  base.plugins = [...(base.plugins || []), new NamespacedChunkIds()];
  return base;
};

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

  return namespaced(base);
};
