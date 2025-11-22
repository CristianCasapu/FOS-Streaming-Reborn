/**
 * Channel Categorization and Mapping Utility
 * Intelligent categorization based on channel names, groups, and patterns
 */

// Category definitions with keywords and patterns
export const CATEGORY_DEFINITIONS = {
    // Sports
    'Sports': {
        keywords: [
            'sport', 'sports', 'espn', 'fox sports', 'bein', 'sky sports', 'eurosport',
            'tennis', 'football', 'soccer', 'basketball', 'nba', 'nfl', 'nhl', 'mlb',
            'formula', 'f1', 'racing', 'golf', 'boxing', 'ufc', 'fight', 'olympic',
            'tsn', 'sportsnet', 'arena', 'dazn', 'premier league', 'laliga', 'bundesliga',
            'serie a', 'ligue 1', 'champions', 'uefa', 'fifa', 'cricket', 'rugby',
            'volleyball', 'handball', 'motorsport', 'cycling', 'athletic'
        ],
        patterns: [
            /sport[s]?/i,
            /\bespn\b/i,
            /\bnba\b|\bnfl\b|\bnhl\b|\bmlb\b/i,
            /\bf1\b|formula/i,
            /\bufc\b|fight/i,
            /premier|champions|uefa/i
        ],
        priority: 10
    },

    // News
    'News': {
        keywords: [
            'news', 'cnn', 'bbc news', 'fox news', 'msnbc', 'cnbc', 'sky news',
            'al jazeera', 'france 24', 'euronews', 'rtl', 'rai news', 'trt world',
            'dw', 'bloomberg', 'reuters', 'abc news', 'cbs news', 'nbc news',
            'headline', 'breaking', 'live news', 'world news', 'business news'
        ],
        patterns: [
            /\bnews\b/i,
            /\bcnn\b|\bbbc\b/i,
            /headline|breaking/i,
            /world.*news|news.*world/i
        ],
        priority: 9
    },

    // Entertainment
    'Entertainment': {
        keywords: [
            'entertainment', 'e!', 'mtv', 'vh1', 'comedy', 'variety', 'talk show',
            'reality', 'talent', 'music tv', 'pop', 'entertainment tonight',
            'celebrity', 'gossip', 'fashion tv', 'lifestyle', 'entertainment network'
        ],
        patterns: [
            /entertainment/i,
            /\bmtv\b|\bvh1\b/i,
            /comedy|variety/i,
            /reality|talent/i,
            /fashion.*tv|lifestyle/i
        ],
        priority: 5
    },

    // Movies
    'Movies': {
        keywords: [
            'movie', 'movies', 'cinema', 'film', 'hbo', 'cinemax', 'starz',
            'showtime', 'flix', 'premiere', 'tcm', 'amc', 'movie channel',
            'hollywood', 'bollywood', 'action movies', 'thriller', 'drama channel',
            'classic movies', 'movie network', 'film4', 'sky cinema'
        ],
        patterns: [
            /\bmovie[s]?\b/i,
            /cinema|film/i,
            /\bhbo\b|cinemax|starz|showtime/i,
            /hollywood|bollywood/i,
            /premiere|classic/i
        ],
        priority: 8
    },

    // Kids
    'Kids': {
        keywords: [
            'kids', 'children', 'cartoon', 'disney', 'nickelodeon', 'nick jr',
            'cartoon network', 'boomerang', 'baby', 'junior', 'toonami',
            'pbs kids', 'cbbc', 'cbeebies', 'animax', 'tiji', 'gulli',
            'baby tv', 'nick toons', 'disney junior', 'disney xd'
        ],
        patterns: [
            /\bkids\b|children/i,
            /cartoon|toon/i,
            /disney|nickelodeon|nick/i,
            /\bjr\b|junior|baby/i,
            /cbbc|cbeebies/i
        ],
        priority: 9
    },

    // Documentary
    'Documentary': {
        keywords: [
            'discovery', 'national geographic', 'nat geo', 'history', 'documentary',
            'science', 'nature', 'wildlife', 'animal planet', 'investigation',
            'crime investigation', 'travel', 'adventure', 'explore', 'knowledge',
            'smithsonian', 'biography', 'history channel', 'natgeo', 'discovery science',
            'discovery turbo', 'viasat', 'dmax', 'investigation discovery'
        ],
        patterns: [
            /discovery|natgeo|nat.*geo/i,
            /history|documentary/i,
            /science|nature|wildlife/i,
            /animal.*planet|investigation/i,
            /smithsonian|biography/i
        ],
        priority: 8
    },

    // Music
    'Music': {
        keywords: [
            'music', 'mtv music', 'vh1 classic', 'viva', 'trace', 'mezzo',
            'stingray', 'box', 'clubbing', 'dance', 'rock', 'jazz', 'classical music',
            'opera', 'concert', 'music channel', 'music box', 'hit music',
            'deluxe music', 'radio', 'audio'
        ],
        patterns: [
            /\bmusic\b/i,
            /\bmtv\b.*music|\bvh1\b.*classic/i,
            /jazz|classical|opera|concert/i,
            /rock|dance|clubbing/i,
            /stingray|mezzo/i
        ],
        priority: 7
    },

    // Religious
    'Religious': {
        keywords: [
            'religious', 'religion', 'church', 'faith', 'gospel', 'christian',
            'catholic', 'islamic', 'muslim', 'spiritual', 'prayer', 'bible',
            'quran', 'buddhist', 'hindu', 'jewish', 'ewtn', 'tbn', 'god',
            'worship', 'sermon', 'ministry', 'pilgrimage'
        ],
        patterns: [
            /religious|religion|faith/i,
            /church|gospel|christian/i,
            /islamic|muslim|quran/i,
            /spiritual|prayer|worship/i,
            /\bewtn\b|\btbn\b/i
        ],
        priority: 6
    },

    // General/Entertainment
    'General': {
        keywords: [
            'general', 'variety', 'family', 'one', 'two', 'three', 'tv1', 'tv2',
            'channel', 'network', 'plus', 'hd', 'first', 'prime', 'main'
        ],
        patterns: [
            /\btv[0-9]\b/i,
            /general|variety/i,
            /\bone\b|\btwo\b|\bthree\b/i,
            /channel|network/i
        ],
        priority: 1
    },

    // 24/7 Series/Shows
    '24/7': {
        keywords: [
            '24/7', '24h', 'non-stop', 'marathon', 'all day', 'continuous'
        ],
        patterns: [
            /24\/?7|24\s*h/i,
            /non[-\s]?stop|marathon/i,
            /all.*day|continuous/i
        ],
        priority: 4
    },

    // Adult (18+)
    'Adult': {
        keywords: [
            'xxx', 'adult', 'playboy', 'hustler', 'penthouse', 'brazzers',
            '18+', '+18', 'erotic', 'sexy', 'hot tv', 'venus', 'redlight'
        ],
        patterns: [
            /\bxxx\b|\badult\b/i,
            /18\+|\+18/i,
            /playboy|hustler|penthouse/i,
            /erotic|sexy/i
        ],
        priority: 10
    },

    // Regional/Local
    'Regional': {
        keywords: [
            'local', 'regional', 'city', 'county', 'state tv', 'municipal',
            'community', 'provincial'
        ],
        patterns: [
            /local|regional/i,
            /city|county|municipal/i,
            /community|provincial/i
        ],
        priority: 3
    },

    // Shopping
    'Shopping': {
        keywords: [
            'shop', 'shopping', 'qvc', 'hsn', 'home shopping', 'teleshopping',
            'shopping channel', 'shop tv', 'retail'
        ],
        patterns: [
            /shop/i,
            /\bqvc\b|\bhsn\b/i,
            /home.*shopping|teleshopping/i
        ],
        priority: 6
    },

    // Educational
    'Educational': {
        keywords: [
            'education', 'learning', 'academic', 'university', 'school',
            'educational', 'knowledge', 'teach', 'lecture', 'course'
        ],
        patterns: [
            /education|learning/i,
            /academic|university|school/i,
            /teach|lecture|course/i
        ],
        priority: 7
    }
};

// Country/Region definitions with patterns and variations
export const COUNTRY_DEFINITIONS = {
    'USA': {
        keywords: ['usa', 'us', 'united states', 'america', 'american'],
        patterns: [/\busa\b|\bus\b/i, /united\s*states|america/i],
        codes: ['US', 'USA']
    },
    'UK': {
        keywords: ['uk', 'united kingdom', 'britain', 'british', 'england', 'scotland', 'wales'],
        patterns: [/\buk\b/i, /united\s*kingdom|britain|british/i],
        codes: ['UK', 'GB']
    },
    'Canada': {
        keywords: ['canada', 'canadian', 'ca'],
        patterns: [/canada|canadian/i],
        codes: ['CA', 'CAN']
    },
    'Germany': {
        keywords: ['germany', 'german', 'deutschland', 'de'],
        patterns: [/germany|german|deutschland/i],
        codes: ['DE', 'GER']
    },
    'France': {
        keywords: ['france', 'french', 'français', 'fr'],
        patterns: [/france|french|français/i],
        codes: ['FR', 'FRA']
    },
    'Spain': {
        keywords: ['spain', 'spanish', 'españa', 'español', 'es'],
        patterns: [/spain|spanish|españa|español/i],
        codes: ['ES', 'ESP']
    },
    'Italy': {
        keywords: ['italy', 'italian', 'italia', 'italiano', 'it'],
        patterns: [/italy|italian|italia/i],
        codes: ['IT', 'ITA']
    },
    'Netherlands': {
        keywords: ['netherlands', 'dutch', 'holland', 'nl'],
        patterns: [/netherlands|dutch|holland/i],
        codes: ['NL', 'NLD']
    },
    'Poland': {
        keywords: ['poland', 'polish', 'polska', 'pl'],
        patterns: [/poland|polish|polska/i],
        codes: ['PL', 'POL']
    },
    'Romania': {
        keywords: ['romania', 'romanian', 'românia', 'ro'],
        patterns: [/romania|romanian|românia/i],
        codes: ['RO', 'ROU']
    },
    'Turkey': {
        keywords: ['turkey', 'turkish', 'türkiye', 'tr'],
        patterns: [/turkey|turkish|türkiye/i],
        codes: ['TR', 'TUR']
    },
    'Greece': {
        keywords: ['greece', 'greek', 'ελλάδα', 'gr'],
        patterns: [/greece|greek|ελλάδα/i],
        codes: ['GR', 'GRC']
    },
    'Portugal': {
        keywords: ['portugal', 'portuguese', 'português', 'pt'],
        patterns: [/portugal|portuguese|português/i],
        codes: ['PT', 'PRT']
    },
    'Arabic': {
        keywords: ['arab', 'arabic', 'عربي', 'middle east', 'ar'],
        patterns: [/arab|arabic|عربي/i, /middle\s*east/i],
        codes: ['AR', 'AE', 'SA']
    },
    'India': {
        keywords: ['india', 'indian', 'hindi', 'in'],
        patterns: [/india|indian|hindi/i],
        codes: ['IN', 'IND']
    },
    'Russia': {
        keywords: ['russia', 'russian', 'россия', 'ru'],
        patterns: [/russia|russian|россия/i],
        codes: ['RU', 'RUS']
    },
    'Albania': {
        keywords: ['albania', 'albanian', 'shqipëri', 'al'],
        patterns: [/albania|albanian|shqipëri/i],
        codes: ['AL', 'ALB']
    },
    'Brazil': {
        keywords: ['brazil', 'brazilian', 'brasil', 'br'],
        patterns: [/brazil|brazilian|brasil/i],
        codes: ['BR', 'BRA']
    },
    'International': {
        keywords: ['international', 'world', 'global', 'multi'],
        patterns: [/international|world|global/i],
        codes: ['INT', 'WORLD']
    }
};

/**
 * Categorize a stream based on name and group
 */
export function categorizeStream(streamName, groupName = '') {
    const combinedText = `${streamName} ${groupName}`.toLowerCase();
    let bestMatch = null;
    let bestScore = 0;

    // Check each category
    for (const [categoryName, definition] of Object.entries(CATEGORY_DEFINITIONS)) {
        let score = 0;

        // Check keywords
        for (const keyword of definition.keywords) {
            if (combinedText.includes(keyword.toLowerCase())) {
                score += 2;
            }
        }

        // Check patterns
        for (const pattern of definition.patterns) {
            if (pattern.test(combinedText)) {
                score += 3;
            }
        }

        // Apply priority weight
        score *= (definition.priority / 10);

        if (score > bestScore) {
            bestScore = score;
            bestMatch = categoryName;
        }
    }

    return bestMatch || 'General';
}

/**
 * Detect country/region from group or stream name
 */
export function detectCountry(groupName, streamName = '') {
    const combinedText = `${groupName} ${streamName}`.toLowerCase();

    for (const [country, definition] of Object.entries(COUNTRY_DEFINITIONS)) {
        // Check keywords
        for (const keyword of definition.keywords) {
            if (combinedText.includes(keyword.toLowerCase())) {
                return country;
            }
        }

        // Check patterns
        for (const pattern of definition.patterns) {
            if (pattern.test(combinedText)) {
                return country;
            }
        }

        // Check country codes
        for (const code of definition.codes) {
            const codePattern = new RegExp(`\\b${code}\\b`, 'i');
            if (codePattern.test(combinedText)) {
                return country;
            }
        }
    }

    return null;
}

/**
 * Create category name combining country and content type
 */
export function generateCategoryName(country, contentType) {
    if (country && contentType !== 'General') {
        return `${country} - ${contentType}`;
    } else if (country) {
        return country;
    } else {
        return contentType;
    }
}

/**
 * Auto-map streams with intelligent categorization
 */
export function autoMapStreams(streams) {
    const categoryMap = new Map();
    const results = [];

    for (const stream of streams) {
        // Detect country and content type
        const country = detectCountry(stream.group || '', stream.name);
        const contentType = categorizeStream(stream.name, stream.group || '');

        // Generate category name
        const categoryName = generateCategoryName(country, contentType);

        // Track unique categories
        if (!categoryMap.has(categoryName)) {
            categoryMap.set(categoryName, {
                name: categoryName,
                country: country,
                contentType: contentType,
                streams: []
            });
        }

        // Add stream to category
        categoryMap.get(categoryName).streams.push(stream);

        results.push({
            ...stream,
            detectedCountry: country,
            detectedContentType: contentType,
            suggestedCategory: categoryName
        });
    }

    return {
        streams: results,
        categories: Array.from(categoryMap.values()),
        categoryMap: Object.fromEntries(categoryMap)
    };
}

/**
 * Get category suggestions for a stream
 */
export function getCategorySuggestions(streamName, groupName = '') {
    const country = detectCountry(groupName, streamName);
    const contentType = categorizeStream(streamName, groupName);

    const suggestions = [];

    // Primary suggestion
    const primary = generateCategoryName(country, contentType);
    suggestions.push({
        name: primary,
        confidence: 'high',
        reason: `Detected: ${country || 'Unknown region'} + ${contentType}`
    });

    // Alternative: Just content type
    if (contentType !== 'General') {
        suggestions.push({
            name: contentType,
            confidence: 'medium',
            reason: `Content type only`
        });
    }

    // Alternative: Just country
    if (country) {
        suggestions.push({
            name: country,
            confidence: 'medium',
            reason: `Region only`
        });
    }

    return suggestions;
}

/**
 * Validate and clean category name
 */
export function cleanCategoryName(name) {
    return name
        .trim()
        .replace(/\s+/g, ' ')
        .replace(/[^\w\s-]/g, '')
        .substring(0, 100);
}
