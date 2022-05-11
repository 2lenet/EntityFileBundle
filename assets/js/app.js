import Dropzone from "dropzone";

let onLoad = (callback) => {
    if (document.readyState !== "loading") {
        callback();
    } else {
        document.addEventListener("DOMContentLoaded", callback);
    }
}

onLoad(() => {
    document.querySelectorAll(".lle-entity-dropzone").forEach((e) => {
        let dropzone = new Dropzone(e);
    });
});
