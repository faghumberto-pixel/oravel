module.exports = {
  layout: "post.njk",
  eleventyComputed: {
    permalink: (data) => {
      if (data.page && data.page.fileSlug) {
        return `/la-fora/${data.page.fileSlug}/`;
      }
    }
  }
};