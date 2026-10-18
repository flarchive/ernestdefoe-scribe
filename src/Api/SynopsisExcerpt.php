<?php

namespace ErnestDefoe\Scribe\Api;

use Closure;
use ErnestDefoe\Scribe\Formatter\ReplyGate;
use Flarum\Api\Context;
use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use s9e\TextFormatter\Utils;
use Tobyz\JsonApiServer\Schema\Field\Field;

/**
 * Keeps "reply to view" content out of fof/synopsis's discussion-list excerpt.
 *
 * 🚨 Synopsis builds `synopsisExcerpt` from the post's STORED XML, not its
 * rendered HTML, so ReplyGate never sees it: without this the opening words of
 * a gated block were in the discussion list for everyone, guests included.
 * (Rich excerpts render the post, so ReplyGate already covers those.)
 *
 * Synopsis's own getter still does all the work. This wraps it: the outer call
 * notes the discussion so one "has replied" query covers the whole page, and
 * the deferred value is swapped only when the excerpt post has a gate the
 * viewer may not see. Everyone else, and every post without a gate, gets
 * Synopsis's value untouched.
 */
class SynopsisExcerpt
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(Field $field): Field
    {
        // The getter is protected and has no accessor; read it in scope.
        $original = Closure::bind(fn () => $this->getter, $field, Field::class)();

        if (! $original instanceof Closure) {
            return $field; // Synopsis changed shape; leave it alone rather than guess.
        }

        return $field->get(function (Discussion $discussion, Context $context) use ($original) {
            $value = $original($discussion, $context);

            if (! $value instanceof Closure) {
                return $this->gated($value, $discussion, $context);
            }

            ReplyGate::expect($context->request, (int) $discussion->id);

            return fn () => $this->gated($value(), $discussion, $context);
        });
    }

    private function gated(mixed $excerpt, Discussion $discussion, Context $context): mixed
    {
        if (! is_string($excerpt)) {
            return $excerpt;
        }

        $relation = $this->settings->get('fof-synopsis.excerpt-type') === 'last' ? 'lastPost' : 'firstPost';
        $post = $discussion->relationLoaded($relation) ? $discussion->getRelation($relation) : null;

        if (! $post instanceof CommentPost) {
            return $excerpt;
        }

        $xml = (string) $post->parsed_content;

        if (! str_contains($xml, '<SCRIBEREPLY')
            || ReplyGate::entitled($context->getActor(), $post, $context->request, $discussion)) {
            return $excerpt;
        }

        return self::withheld($xml, $excerpt);
    }

    /**
     * The excerpt Synopsis would have built from this XML with its gates
     * emptied. Pure, so it is tested against the real formatter.
     */
    public static function withheld(string $xml, string $excerpt): ?string
    {
        try {
            $plain = trim(preg_replace('/\s+/', ' ', Utils::removeFormatting(ReplyGate::withhold($xml))) ?? '');
        } catch (\Throwable) {
            return null;
        }

        // Withholding only removes text, so Synopsis's own length is the cap:
        // shorter than its limit means it was never cut, equal means cut there.
        return $plain === '' ? null : mb_substr($plain, 0, mb_strlen($excerpt));
    }
}
