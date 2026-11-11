module.exports = {
  layout: "post.njk",
  eleventyComputed: {
    permalink: (data) => {
      if (data.page && data.page.fileSlug) {
        return `/comunidade/${data.page.fileSlug}/`;
      }
    }
  }
};