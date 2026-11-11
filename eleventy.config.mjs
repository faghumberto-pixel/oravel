const TZ = "America/Sao_Paulo";

export default function (eleventyConfig) {
  eleventyConfig.addPassthroughCopy({ "src/assets": "assets" });
  eleventyConfig.addPassthroughCopy({ "src/robots.txt": "robots.txt" });
  // Páginas estáticas antigas (glossário, downloads etc.) entram aqui, se existirem.
  eleventyConfig.addPassthroughCopy({ "estatico": "." });

  eleventyConfig.addFilter("dataLonga", (d) =>
    new Intl.DateTimeFormat("pt-BR", { dateStyle: "long", timeZone: TZ }).format(new Date(d)));
  eleventyConfig.addFilter("hora", (d) =>
    new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit", timeZone: TZ }).format(new Date(d)));
  eleventyConfig.addFilter("dataIso", (d) => new Date(d).toISOString());
  eleventyConfig.addFilter("rfc822", (d) => new Date(d).toUTCString());
  eleventyConfig.addFilter("head", (arr, n) => arr.slice(0, n));
  eleventyConfig.addFilter("doTema", (posts, slug) => posts.filter((p) => p.data.theme === slug));

  eleventyConfig.addCollection("posts", (api) =>
    api.getFilteredByGlob("src/content/**/*.md").sort((a, b) => b.date - a.date));

  return {
    dir: { input: "src", output: "_site", includes: "_includes", data: "_data" },
    markdownTemplateEngine: "njk",
    htmlTemplateEngine: "njk",
  };
}
