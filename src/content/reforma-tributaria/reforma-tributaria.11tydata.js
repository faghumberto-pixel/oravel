module.exports = {
  layout: "post.njk",
  eleventyComputed: {
    permalink: (data) => {
      if (data.page && data.page.fileSlug) {
        return `/reforma-tributaria/${data.page.fileSlug}/`;
      }
    }
  }
};