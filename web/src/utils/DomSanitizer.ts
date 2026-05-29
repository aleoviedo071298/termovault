import DOMPurify from "dompurify";

/**
 * Sanitize HTML content to prevent XSS
 * Whitelist: <p>, <br>, <strong>, <em>, <ul>, <li>, <a>
 */
export function sanitizeHtml(dirty: string): string {
  const config = {
    ALLOWED_TAGS: ["p", "br", "strong", "em", "ul", "li", "a"],
    ALLOWED_ATTR: ["href", "target", "rel"],
    KEEP_CONTENT: true,
  };
  return DOMPurify.sanitize(dirty, config);
}

/**
 * Sanitize user input to prevent XSS in dynamically rendered content.
 * Strips HTML tags but preserves text content (including script tag inner contents).
 */
export function sanitizeUserInput(input: string): string {
  // Strip HTML tags using regex first to preserve inner text contents
  const stripped = input.replace(/<[^>]*>/g, "");
  return DOMPurify.sanitize(stripped, {
    ALLOWED_TAGS: [],
    ALLOWED_ATTR: [],
  });
}
