module.exports = {
  layout: "post.njk",
  eleventyComputed: {
    permalink: (data) => {
      if (data.page && data.page.fileSlug) {
        return `/tecnologia/${data.page.fileSlug}/`;
      }
    }
  }
};