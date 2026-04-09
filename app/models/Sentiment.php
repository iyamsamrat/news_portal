<?php
declare(strict_types=1);

/**
 * Sentiment Analysis Model  — v3
 * app/models/Sentiment.php
 *
 * Lexicon-Based Sentiment Analysis (VADER-inspired)
 * Supports: English · Romanised Nepali · Devanagari Nepali · Emojis
 *
 * Algorithm
 * ---------
 * 1.  Score emojis directly from raw text (before stripping).
 * 2.  Pre-process: lowercase, collapse apostrophes in contractions
 *     (don't → dont), strip punctuation, tokenise on whitespace.
 * 3.  For each token, try the token as-is; if no hit, try a
 *     Devanagari suffix-stripped form (राम्रोको → राम्रो).
 * 4.  Walk each token through five rule layers in order:
 *       negation     → set a 3-token "flip" window
 *       intensifier  → multiplier = 1.5
 *       diminisher   → multiplier = 0.5
 *       positive hit → raw += (+/−1) × multiplier
 *       negative hit → raw += (−/+1) × multiplier
 * 5.  Normalise: score = raw / √(raw² + α),  α = 15
 * 6.  Classify: score > 0.05 → positive | < −0.05 → negative | else neutral
 */

require_once __DIR__ . '/../config/db.php';

final class Sentiment
{
    private const ALPHA = 15;

    // ─── Emoji direct scores (checked on raw text before stripping) ───
    private const EMOJI_SCORES = [
        // ── Strongly positive ───────────────────────────────────────
        '😍' => 2.0, '🥰' => 2.0, '🤩' => 2.0, '😘' => 1.5,
        '😊' => 1.5, '😄' => 1.5, '😁' => 1.5, '😀' => 1.5, '😃' => 1.5,
        '🙂' => 0.8, '😌' => 0.8, '🤗' => 1.2,
        '❤️' => 2.0, '❤' => 1.5, '💕' => 1.5, '💖' => 1.5, '💗' => 1.5,
        '💓' => 1.5, '💞' => 1.5, '💝' => 1.5, '🧡' => 1.2, '💛' => 1.2,
        '💚' => 1.2, '💙' => 1.2, '💜' => 1.2,
        '👍' => 1.5, '👌' => 1.2, '🤝' => 1.0, '✌️' => 0.8, '✌' => 0.8,
        '🎉' => 1.5, '🎊' => 1.5, '🏆' => 1.5, '🥇' => 1.5,
        '🌟' => 1.5, '⭐' => 1.2, '✨' => 1.2, '💫' => 1.0,
        '💯' => 1.5, '✅' => 1.0, '💪' => 1.0, '👏' => 1.5, '🙏' => 0.8,
        '🔥' => 1.2, '🌺' => 1.0, '🌸' => 0.8,
        // ── Strongly negative ───────────────────────────────────────
        '😢' => -1.5, '😭' => -2.0, '😿' => -1.5,
        '😠' => -1.5, '😡' => -2.0, '🤬' => -2.0, '😤' => -1.0,
        '👎' => -1.5, '💔' => -1.5,
        '😞' => -1.5, '😔' => -1.0, '😟' => -1.0, '😕' => -0.8,
        '🤮' => -2.0, '🤢' => -1.5, '😰' => -1.0, '😨' => -1.0,
        '😱' => -1.5, '😣' => -1.0, '😖' => -1.0,
        '💀' => -1.5, '☠️' => -1.5,
    ];

    // ─── Positive lexicon ─────────────────────────────────────────────
    private const POSITIVE_WORDS = [
        // ── English: general ──────────────────────────────────────────
        'good','great','excellent','wonderful','amazing','fantastic',
        'outstanding','superb','brilliant','exceptional','remarkable',
        'perfect','awesome','splendid','magnificent','marvelous',
        'terrific','fabulous','impressive','admirable','commendable',
        'nice','lovely','beautiful','gorgeous','stunning','cool',
        'wow','love','like','liked','enjoy','enjoyed','enjoying',
        'best','better','helpful','useful','informative','interesting',
        'inspiring','delightful','pleasant','pleasing','charming',
        'glad','happy','joyful','pleased','delighted','cheerful',
        'grateful','thankful','appreciative','content','satisfied',
        'excited','enthusiastic','optimistic','hopeful','confident',
        'proud','inspired','passionate','encouraged','blessed',
        'success','successful','achievement','accomplish','progress',
        'growth','improvement','advance','development','milestone',
        'victory','win','triumph','breakthrough','innovation',
        'effective','efficient','productive','profitable','beneficial',
        'approve','agree','support','endorse','recommend','praise',
        'celebrate','honor','respect','appreciate','value','trust',
        'reliable','dependable','honest','transparent','fair',
        'just','responsible','credible','legitimate',
        'safe','secure','stable','healthy','strong','robust','thriving',
        'prosperous','flourishing','growing','expanding','rising',
        'increasing','improving','recovering','succeeding','advancing',
        'reform','initiative','opportunity','solution','resolve',
        'cooperation','collaboration','partnership','unity','peace',
        'harmony','equality','justice','freedom','democracy',
        'well','correctly','properly','perfectly','wonderfully',
        'top','quality','worthy','valuable',
        'kudos','bravo','congratulations','congrats',
        'correct','right','true','accurate',
        'meaningful','positive','kindness','generous','compassion',
        'motivating','uplifting','heartwarming','wholesome',
        // ── English: informal / comment style ─────────────────────────
        'yay','woah','haha','lol','lmao','hehe','hihi',
        'goat','lit','fire','epic','iconic','based',
        'agree','agreed','yep','yes','absolutely','definitely',
        'thanks','thankyou','thx','ty','np','welcome',
        'helpful','usefull','must','read','mustread',
        // ── Romanised Nepali ──────────────────────────────────────────
        'ramro','ramrai','ramrako','sundar','sundara','sundari',
        'khushi','khusiyali','khus',
        'safal','saphalta','sahi','uchit',
        'thik','thikai','thikkai',
        'pragati','vikas','unnati','shanti','samriddhi',
        'achha','acha','mast','dam','damdaar',
        'badiya','badhiya','waah','wah','shabash',
        'shukriya','dhanyabad','dhanyabaad',
        'ekdam','ekdamai','mitho',
        'manparne','manparyo','manparchha',
        'ujyalo','ujjwal','prashansa','jit','vijaya',
        'adbhut','shandar','umang','harsha','ananda',
        'maya','prem','pyar','sneha','mamata',
        'asha','bharosa','biswas','sahayog','ekata','milap',
        'jabra','jabbar','jabardast','lajawab','lajawaab',
        'mast','dherai','khub',
        'hajur','sanchai','swastha',
        // ── Devanagari Nepali — single-token forms only ───────────────
        'राम्रो','राम्रा','राम्रै','सुन्दर','सुन्दरी',
        'खुशी','खुसी','खुशियाली',
        'धन्यवाद','उत्कृष्ट','बढिया',
        'मिठो','अद्भुत','शानदार','शाबाश','वाह',
        'सफल','सफलता','विजय','जित',
        'शान्ति','प्रेम','माया','प्यार',
        'मनपर्छ','मनपर्यो','लाग्यो',
        'सकारात्मक','उत्साहजनक','प्रशंसनीय','प्रशंसा',
        'शुभ','शुभकामना','बधाई','धन्य',
        'राम्ररी','सहयोग','एकता','मिलाप',
        'ठीक','सही','दमदार','जबरजस्त','एकदम',
        'उज्यालो','उज्जवल','उज्ज्वल',
        'आशा','भरोसा','विश्वास',
        'उत्तम','असल','मजा','मजाको','रमाइलो',
        'आनन्द','हर्ष','हर्षित','प्रसन्न','हँसिलो',
        'प्रगति','विकास','उन्नति','समृद्धि','उत्थान',
        'सुरक्षित','स्वस्थ','बलियो','मजबुत',
        'उत्साह','प्रेरणा','अभिप्रेरण',
        'मनोरञ्जक','मनोरञ्जन','रमाउनु','रमाइलो',
        'लाजवाब','जबर','मस्त','हाँसो','हाँसिलो',
        'उत्साहित','खुसियाली','हर्षित',
        'सञ्चो','स्वास्थ्य','स्वस्थता',
        'सफलतापूर्वक','प्रभावकारी','उपयोगी',
        'महान','महानता','श्रेष्ठ','श्रेष्ठता',
        'लोकप्रिय','प्रसिद्ध','चर्चित',
        'राष्ट्रिय','गर्वित','गौरवशाली',
    ];

    // ─── Negative lexicon ─────────────────────────────────────────────
    private const NEGATIVE_WORDS = [
        // ── English: general ──────────────────────────────────────────
        'bad','terrible','awful','horrible','dreadful','disgusting',
        'appalling','atrocious','deplorable','abysmal','pathetic',
        'lousy','poor','inferior','substandard','deficient','flawed',
        'worst','hate','hated','dislike','disliked',
        'boring','bored','dull','lame','stupid','ridiculous','nonsense',
        'garbage','trash','junk','waste','useless','worthless','pointless',
        'wrong','incorrect','inaccurate','misleading','false','fake',
        'angry','furious','outraged','frustrated','disappointed',
        'sad','unhappy','miserable','depressed','hopeless','desperate',
        'worried','anxious','fearful','scared','distressed','troubled',
        'upset','offended','insulted','hurt','betrayed','humiliated',
        'failure','fail','defeat','loss','decline','downfall','collapse',
        'crisis','disaster','catastrophe','tragedy','calamity',
        'problem','issue','conflict','dispute','controversy',
        'corruption','scandal','misconduct','negligence','incompetence',
        'criticize','condemn','reject','oppose','protest','blame',
        'accuse','attack','threaten','abuse','exploit','manipulate',
        'deceive','mislead','lie','cheat','fraud','scam',
        'danger','risk','threat','harm','damage','destruction','violence',
        'crime','illegal','unlawful','unjust','unfair','discrimination',
        'inequality','poverty','suffering','pain','agony','misery',
        'impossible','powerless','helpless','reckless','careless',
        'irresponsible','unethical','immoral','corrupt',
        'shocking','disgraceful','shameful','embarrassing',
        'broken','failed','damaged','weak',
        // ── English: informal / comment style ─────────────────────────
        'ugh','yikes','smh','wtf','bruh','cringe',
        'overrated','underwhelming','disappointing','letdown',
        'clickbait','mislead','biased','propaganda',
        'nope','nah','disagree','wrong','lies',
        // ── Romanised Nepali ──────────────────────────────────────────
        'naramro','naramra','galat','kharab','kharaba',
        'dukkha','dukha','dukhi',
        'samasya','bipad','bhrastachar','himsa',
        'asaphalta','asaphal','bibad','sankat',
        'jhut','jhuta','bekaar','bekar','nafrat',
        'krodha','ris','risa',
        'durghatana','mrityu','hatya','yuddha',
        'dar','chinta','nindha','dosh','kasur','aparadha',
        'pida','kastha','durbhagya','nirasha',
        'ghatiya','nirdayi','krur',
        'nikammo','fohor','ghatak','bibhed','jhagada',
        // ── Devanagari Nepali — single-token forms only ───────────────
        'नराम्रो','नराम्रा','खराब','दुःख','दुख','दुःखद','दुखद',
        'समस्या','गलत','भ्रष्टाचार','हिंसा',
        'असफल','असफलता',
        'क्रोध','रिस','दुर्घटना','मृत्यु','हत्या',
        'युद्ध','संकट','डर','चिन्ता','चिंता',
        'झुट','झुटो','बेकार','घृणा','नफरत',
        'अपराध','पीडा','कष्ट','विपद','दुर्भाग्य',
        'निराशा','नकारात्मक','असन्तुष्ट','शोक',
        'पश्चाताप','खेद','दोष',
        'हानि','नोक्सान','क्षति','बर्बाद',
        'दुखद','हृदयविदारक','विनाशकारी',
        'अन्याय','भेदभाव','उत्पीडन','शोषण',
        'क्रूर','निर्दयी','अमानवीय','घृणित',
        'खतरनाक','खतरा','भय','त्रास',
        'भ्रामक','अविश्वसनीय','शर्मनाक',
        'महामारी','बिरामी','पीडित',
        'निकम्मो','फोहोर','घातक','झगडा','विभेद',
        'हानिकारक','जोखिम','दुखी','रुनु','रडाइ',
        'भ्रष्ट','दुर्व्यवहार','उत्पात','अव्यवस्था',
        'दुर्बल','कमजोर','पराजित','हराउनु',
    ];

    // ─── Negation words ───────────────────────────────────────────────
    private const NEGATION_WORDS = [
        // English (with apostrophe — matched before tokeniser strips)
        'not','no','never','neither','nor','nobody','nothing',
        'nowhere','none','hardly','barely','scarcely','without',
        "don't","doesn't","didn't","won't","wouldn't","can't",
        "couldn't","shouldn't","isn't","aren't","wasn't","weren't",
        "haven't","hasn't","hadn't",
        // English (after apostrophe collapse: dont, doesnt …)
        'dont','doesnt','didnt','wont','wouldnt','cant',
        'couldnt','shouldnt','isnt','arent','wasnt','werent',
        'havent','hasnt','hadnt',
        // Romanised Nepali negation
        'hoina','hoena','chaina','chhaina','chhainan',
        'nabhayeko','nagarnu','nagarne','napayeko',
        'bina','binakar',
        // Devanagari negation
        'छैन','छैनन्','होइन','होईन','नभएको',
        'बिना','भएन','नाई','नगर','नहोस',
        'छैन','भएन','नभएको','नगरी',
    ];

    // ─── Intensifiers ────────────────────────────────────────────────
    private const INTENSIFIERS = [
        // English
        'very','extremely','incredibly','absolutely','completely',
        'totally','utterly','highly','deeply','strongly','greatly',
        'immensely','enormously','terribly','awfully','severely',
        'exceptionally','remarkably','extraordinarily','particularly',
        'so','really','super','mega','ultra','truly','most',
        'damn','bloody','insanely','unbelievably',
        // Romanised Nepali
        'dherai','dherainai','ati','atinai','ekadam','ekdamai',
        'nikai','khub','khuba','bahut',
        // Devanagari
        'धेरै','अति','एकदम','निकै','खुब','बहुत','धेरैनै',
    ];

    // ─── Diminishers ─────────────────────────────────────────────────
    private const DIMINISHERS = [
        // English
        'slightly','somewhat','fairly','rather','partially',
        'marginally','mildly','almost','nearly','just','merely',
        'kind','little','bit','barely',
        // Romanised Nepali
        'ali','thorai','kehi','kei','alikai',
        // Devanagari
        'अलि','थोरै','केही','अलिकति',
    ];

    /**
     * Common Devanagari case/aspect suffixes — longest first.
     * Stripping these from inflected forms recovers the base lemma.
     * e.g.  राम्रोको → राम्रो,  खुशीसँग → खुशी
     */
    private const DEVA_SUFFIXES = [
        'देखि',   // ablative "from/since"  (4 chars)
        'सँगै',   // comitative+emphatic    (4 chars)
        'तर्फ',   // towards               (3 chars)
        'सँग',    // comitative "with"      (3 chars)
        'बाट',    // ablative "from"        (3 chars)
        'लाई',    // dative "to/for"        (3 chars)
        'नै',     // emphatic particle      (2 chars)
        'को',     // genitive "of"          (2 chars)
        'का',     // genitive pl.           (2 chars)
        'की',     // genitive f.            (2 chars)
        'मा',     // locative "in/at"       (2 chars)
        'ले',     // ergative/instrumental  (2 chars)
        'छ',      // present copula "is"    (1 char)
        'न',      // infinitive/negative    (1 char)
    ];

    private static ?array $positiveSet    = null;
    private static ?array $negativeSet    = null;
    private static ?array $negationSet    = null;
    private static ?array $intensifierSet = null;
    private static ?array $diminisherSet  = null;

    private static function buildSets(): void
    {
        if (self::$positiveSet !== null) return;
        self::$positiveSet    = array_flip(self::POSITIVE_WORDS);
        self::$negativeSet    = array_flip(self::NEGATIVE_WORDS);
        self::$negationSet    = array_flip(self::NEGATION_WORDS);
        self::$intensifierSet = array_flip(self::INTENSIFIERS);
        self::$diminisherSet  = array_flip(self::DIMINISHERS);
    }

    /**
     * Score emojis directly from raw text (before any stripping).
     */
    private static function scoreEmojis(string $text): float
    {
        $score = 0.0;
        foreach (self::EMOJI_SCORES as $emoji => $val) {
            $count = substr_count($text, $emoji);
            if ($count > 0) {
                $score += $val * $count;
            }
        }
        return $score;
    }

    /**
     * Pre-process and tokenise.
     * - Lowercases (Unicode-safe).
     * - Collapses apostrophes in contractions: don't → dont.
     * - Strips remaining punctuation while keeping all Unicode letters
     *   (English, Devanagari, etc.), Unicode marks (Devanagari vowel
     *   signs & virama — category M), and digits.
     * - Splits on whitespace.
     */
    private static function tokenise(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Collapse contractions: letter+apostrophe+letter → letters joined
        $text = (string) preg_replace("/(\p{L})'(\p{L})/u", '$1$2', $text);
        // Keep letters (all scripts), Unicode marks (Devanagari vowel signs
        // and virama are category M, not L — must keep or words break apart),
        // digits, and spaces.
        $text = (string) preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $text);
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * For Devanagari tokens: try stripping common inflectional suffixes
     * (longest first) to recover a base form that may be in the lexicon.
     * Minimum base length is 2 characters to avoid over-stripping.
     * Returns the original word if no suffix produces a shorter valid word.
     */
    private static function stemDevanagari(string $word): string
    {
        // Only process words that actually contain Devanagari characters.
        if (!preg_match('/\p{Devanagari}/u', $word)) {
            return $word;
        }
        $len = mb_strlen($word, 'UTF-8');
        foreach (self::DEVA_SUFFIXES as $suffix) {
            $sLen = mb_strlen($suffix, 'UTF-8');
            if ($len > $sLen + 1 && mb_substr($word, -$sLen, null, 'UTF-8') === $suffix) {
                return mb_substr($word, 0, $len - $sLen, 'UTF-8');
            }
        }
        return $word;
    }

    private static function normalise(float $raw): float
    {
        if ($raw == 0.0) return 0.0;
        return round(max(-1.0, min(1.0, $raw / sqrt($raw * $raw + self::ALPHA))), 4);
    }

    private static function toLabel(float $score): string
    {
        if ($score >  0.05) return 'positive';
        if ($score < -0.05) return 'negative';
        return 'neutral';
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Analyse a text string and return score + label.
     *
     * @return array{score:float, label:string, raw:float, tokens:int}
     */
    public static function analyse(string $text): array
    {
        self::buildSets();

        // Step 1: emoji score on raw text
        $rawScore = self::scoreEmojis($text);

        // Step 2: lexicon walk
        $tokens     = self::tokenise($text);
        $hitCount   = 0;
        $negated    = false;
        $negWin     = 0;    // non-sentiment words since last negation (max 3)
        $multiplier = 1.0;

        foreach ($tokens as $word) {

            // For Devanagari: also prepare the suffix-stripped base form
            $stem = self::stemDevanagari($word);
            // Use $stem for lookups when it differs from $word
            $lookup = ($stem !== $word && mb_strlen($stem, 'UTF-8') > 1) ? $stem : $word;

            // Negation — check both forms
            if (isset(self::$negationSet[$word]) || isset(self::$negationSet[$lookup])) {
                $negated    = true;
                $negWin     = 0;
                $multiplier = 1.0;
                continue;
            }

            // Intensifier
            if (isset(self::$intensifierSet[$word]) || isset(self::$intensifierSet[$lookup])) {
                $multiplier = 1.5;
                continue;
            }

            // Diminisher
            if (isset(self::$diminisherSet[$word]) || isset(self::$diminisherSet[$lookup])) {
                $multiplier = 0.5;
                continue;
            }

            // Positive hit
            if (isset(self::$positiveSet[$word]) || isset(self::$positiveSet[$lookup])) {
                $rawScore += ($negated ? -1.0 : 1.0) * $multiplier;
                $hitCount++;
                $negated = false; $negWin = 0; $multiplier = 1.0;
                continue;
            }

            // Negative hit
            if (isset(self::$negativeSet[$word]) || isset(self::$negativeSet[$lookup])) {
                $rawScore += ($negated ? 1.0 : -1.0) * $multiplier;
                $hitCount++;
                $negated = false; $negWin = 0; $multiplier = 1.0;
                continue;
            }

            // Non-sentiment token: advance negation window (VADER-style 3-token window)
            if ($negated) {
                if (++$negWin >= 3) {
                    $negated = false;
                    $negWin  = 0;
                }
            }
            $multiplier = 1.0;
        }

        $score = self::normalise($rawScore);

        return [
            'score'  => $score,
            'label'  => self::toLabel($score),
            'raw'    => $rawScore,
            'tokens' => $hitCount,
        ];
    }

    public static function analyseAndStoreComment(int $commentId, string $text): array
    {
        $result = self::analyse($text);
        try {
            db()->prepare(
                "UPDATE comments SET sentiment_score = :score, sentiment_label = :label WHERE id = :id"
            )->execute(['score' => $result['score'], 'label' => $result['label'], 'id' => $commentId]);
        } catch (Throwable) {}
        return $result;
    }

    public static function analyseAndStoreArticle(int $articleId, string $text): array
    {
        $result = self::analyse($text);
        try {
            db()->prepare(
                "UPDATE articles SET sentiment_score = :score, sentiment_label = :label WHERE id = :id"
            )->execute(['score' => $result['score'], 'label' => $result['label'], 'id' => $articleId]);
        } catch (Throwable) {}
        return $result;
    }

    /**
     * Aggregate positive/negative/neutral counts for a table.
     *
     * @return array{positive:int, negative:int, neutral:int}
     */
    public static function getBreakdown(string $table): array
    {
        if (!in_array($table, ['comments', 'articles'], true)) {
            return ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        }
        try {
            $rows   = db()->query(
                "SELECT sentiment_label, COUNT(*) AS cnt
                   FROM `{$table}`
                  WHERE sentiment_label IS NOT NULL
                  GROUP BY sentiment_label"
            )->fetchAll() ?: [];
            $result = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
            foreach ($rows as $row) {
                $lbl = (string) ($row['sentiment_label'] ?? '');
                if (isset($result[$lbl])) $result[$lbl] = (int) $row['cnt'];
            }
            return $result;
        } catch (Throwable) {
            return ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        }
    }
}
