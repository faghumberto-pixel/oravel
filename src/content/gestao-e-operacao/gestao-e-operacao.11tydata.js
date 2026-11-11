module.exports = {
  layout: "post.njk",
  eleventyComputed: {
    permalink: (data) => {
      if (data.page && data.page.fileSlug) {
        return `/gestao-e-operacao/${data.page.fileSlug}/`;
      }
    }
  }
};