import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';
import registryJson from '../../../../resources/video-providers.json';

const PROVIDERS: Record<string, { name: string; icon?: string }> = (registryJson as any).providers;

const t = (k: string, p?: Record<string, string>) => app.translator.trans('ernestdefoe-scribe.admin.video.' + k, p as any);

export interface VideoSettingsAttrs {
  /** `ernestdefoe-scribe.video_embeds` stream: "1" / "0". */
  enabled: (value?: string) => string;
  /** `ernestdefoe-scribe.video_providers_off` stream: a JSON list of keys. */
  off: (value?: string) => string;
}

/**
 * Video embeds: one master switch, then one switch per provider.
 *
 * Stored as the providers that are OFF, so a provider added in a later version
 * arrives switched on, like every other one did, instead of silently missing.
 * Writes into the ExtensionPage's own setting streams, so its Save button
 * persists this with everything else.
 */
export default class VideoSettings extends Component<VideoSettingsAttrs> {
  offList(): string[] {
    try {
      const parsed = JSON.parse(this.attrs.off() || '[]');
      return Array.isArray(parsed) ? parsed.filter((k) => typeof k === 'string') : [];
    } catch {
      return [];
    }
  }

  view() {
    const raw = this.attrs.enabled();
    const enabled = !(raw === '0' || raw === '' || (raw as any) === false);
    const off = this.offList();

    return (
      <div className="Form-group Scribe-videoSettings">
        <label>{t('heading')}</label>
        <div className="helpText">{t('help')}</div>
        {Switch.component(
          {
            state: enabled,
            onchange: (value: boolean) => this.attrs.enabled(value ? '1' : '0'),
          },
          t('enabled')
        )}
        <div className={'Scribe-videoProviders' + (enabled ? '' : ' is-disabled')} aria-disabled={enabled ? undefined : 'true'}>
          <div className="Scribe-videoProvidersLabel">{t('providers')}</div>
          <ul className="Scribe-videoProviderList">
            {Object.keys(PROVIDERS).map((key) => (
              <li key={key}>
                {Switch.component(
                  {
                    state: !off.includes(key),
                    disabled: !enabled,
                    onchange: (value: boolean) => {
                      const next = off.filter((k) => k !== key);
                      if (!value) next.push(key);
                      this.attrs.off(JSON.stringify(next));
                    },
                  },
                  [<i className={'icon ' + (PROVIDERS[key].icon || 'fas fa-video')} aria-hidden="true" />, ' ', PROVIDERS[key].name]
                )}
              </li>
            ))}
          </ul>
          <div className="helpText">{t('not_supported')}</div>
        </div>
      </div>
    );
  }
}
