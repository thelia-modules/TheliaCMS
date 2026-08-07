/**
 * The block catalogue, as the server describes it.
 *
 * Nothing about a block is decided here: its label, its sample text and its
 * markup all come from `BlockCatalog` on the server, because the sample text
 * belongs to the language of the page being written and because a project adds
 * a block of its own in PHP, next to the ten it can read.
 *
 * This file only turns that description into entries of the block panel, and
 * gives each one a drawing.
 */
const ICONS = {
    "cms-hero":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 4h18v9H3zm0 11h11v2H3zm0 3h7v2H3z"/></svg>',
    "cms-media-text":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 5h9v14H3zm11 1h7v2h-7zm0 4h7v2h-7zm0 4h7v2h-7z"/></svg>',
    "cms-cta":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 6h18v12H3zm5 4h8v4H8z"/></svg>',
    "cms-quote":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 7h5v5H9.5c0 2 .8 3 2.5 3v2c-3.3 0-5-2-5-5zm9 0h5v5h-2.5c0 2 .8 3 2.5 3v2c-3.3 0-5-2-5-5z"/></svg>',
    "cms-testimonials":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M2 6h6v8H2zm7 0h6v8H9zm7 0h6v8h-6zM2 16h20v2H2z"/></svg>',
    "cms-figures":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 15h4v6H3zm7-6h4v12h-4zm7-6h4v18h-4z"/></svg>',
    "cms-logos":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 9h4v6H3zm6 0h4v6H9zm6 0h4v6h-4z"/></svg>',
    "cms-gallery":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 4h8v8H3zm10 0h8v5h-8zM3 14h8v6H3zm10-3h8v9h-8z"/></svg>',
    "cms-questions":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 5h18v4H3zm0 6h18v4H3zm0 6h18v3H3z"/></svg>',
    "cms-section":
        '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 4h18v16H3zm2 2v12h14V6z"/></svg>',
};

const FALLBACK_ICON =
    '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16v16H4zm2 2v12h12V6z"/></svg>';

export default (editor, options = {}) => {
    const { blocks = [] } = options;
    const catalogIds = new Set(blocks.map((block) => block.id));

    // A block names its own section through `aria-labelledby`, which points at
    // the id of its heading. Dropped twice on the same page, the markup would
    // hold that id twice and both sections would be announced by the first
    // heading, so the ids of a freshly dropped block are made unique here.
    editor.on("block:drag:stop", (component, block) => {
        if (component && block && catalogIds.has(block.getId())) {
            makeIdsUnique(component);
        }
    });

    blocks.forEach((block, index) => {
        editor.Blocks.add(block.id, {
            label: block.label,
            media: ICONS[block.id] ?? FALLBACK_ICON,
            category: block.category,
            // Selected on drop: the first thing an editor does with a block is
            // replace the sample text in it.
            select: true,
            order: index,
            content: block.content,
        });
    });
};

/**
 * Suffixes every id inside a component, and repoints whatever referred to them.
 *
 * Only ids that came with the block are touched: an editor who typed an id of
 * their own on an element keeps it, because the rename follows the references
 * rather than the naming convention.
 */
function makeIdsUnique(component) {
    const suffix = Math.random().toString(36).slice(2, 6);
    const renamed = new Map();
    const parts = [component, ...component.find("*")];

    parts.forEach((part) => {
        const id = part.getAttributes().id;

        if (id) {
            renamed.set(id, `${id}-${suffix}`);
            part.addAttributes({ id: `${id}-${suffix}` });
        }
    });

    if (renamed.size === 0) {
        return;
    }

    parts.forEach((part) => {
        const attributes = part.getAttributes();

        ["aria-labelledby", "aria-describedby", "aria-controls"].forEach((attribute) => {
            const value = attributes[attribute];

            if (value && renamed.has(value)) {
                part.addAttributes({ [attribute]: renamed.get(value) });
            }
        });
    });
}
