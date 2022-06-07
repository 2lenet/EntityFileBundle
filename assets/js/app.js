import Dropzone from "dropzone";

let onLoad = (callback) => {
    if (document.readyState !== "loading") {
        callback();
    } else {
        document.addEventListener("DOMContentLoaded", callback);
    }
}

onLoad(() => {
    document.querySelectorAll(".lle-entity-dropzone").forEach((form) => {
        let options = {
            addRemoveLinks: true,
            ignoreHiddenFiles: false,
            thumbnail: function (file, dataUrl) {

                // override default function to disable thumbnails for non image files
                if (!file.disablePreview && file.previewElement) {
                    file.previewElement.classList.remove("dz-file-preview");
                    for (let thumbnailElement of file.previewElement.querySelectorAll("[data-dz-thumbnail]")){
                        thumbnailElement.alt = file.name;
                        thumbnailElement.src = dataUrl;
                    }

                    return setTimeout(() => file.previewElement.classList.add("dz-image-preview"), 1);
                }
            }
        };
        
        let dropzone = new Dropzone(form, options);
        let existingFiles = JSON.parse(form.dataset.files);

        // https://github.com/dropzone/dropzone/discussions/1909
        for (let file of existingFiles) {
            dropzone.displayExistingFile(file, file.url, null, null, file.resizeThumbnail);
            dropzone.files.push(file);
        }

        // TODO: gestion max fichiers
        // TODO: traduction textes (faire des fichiers js...)
    });
});
