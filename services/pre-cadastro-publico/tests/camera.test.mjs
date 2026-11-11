import test from "node:test";
import assert from "node:assert/strict";
import { CameraSession, photograph } from "../public/camera.js";
function stream() {
    const track = {
        stopped: 0,
        stop() {
            this.stopped++;
        },
    };
    return { track, getTracks: () => [track] };
}
test("câmera abre somente por solicitação, sem microfone e com a direção correta", async () => {
    const calls = [];
    const media = stream();
    const camera = new CameraSession(async (constraints) => {
        calls.push(constraints);
        return media;
    });
    assert.equal(calls.length, 0);
    assert.equal(await camera.open("user"), media);
    assert.equal(calls[0].audio, false);
    assert.equal(calls[0].video.facingMode.ideal, "user");
    camera.close();
    assert.equal(media.track.stopped, 1);
    assert.equal(camera.stream, null);
});
test("fechar durante permissão pendente descarta e encerra a câmera liberada depois", async () => {
    const media = stream();
    let approve;
    const camera = new CameraSession(
        () =>
            new Promise((resolve) => {
                approve = resolve;
            }),
    );
    const pending = camera.open("environment");
    camera.close();
    approve(media);
    assert.equal(await pending, null);
    assert.equal(media.track.stopped, 1);
});
test("trocar captura encerra o stream anterior e não mantém duas câmeras", async () => {
    const first = stream(),
        second = stream();
    let i = 0;
    const camera = new CameraSession(async () => (i++ === 0 ? first : second));
    await camera.open("user");
    await camera.open("environment");
    assert.equal(first.track.stopped, 1);
    assert.equal(second.track.stopped, 0);
    camera.close();
    assert.equal(second.track.stopped, 1);
});
test("permissão negada não deixa stream aberto e permite nova tentativa", async () => {
    let fail = true;
    const media = stream();
    const camera = new CameraSession(async () => {
        if (fail) throw new Error("negada");
        return media;
    });
    await assert.rejects(camera.open("user"), /negada/);
    assert.equal(camera.stream, null);
    fail = false;
    assert.equal(await camera.open("user"), media);
    camera.close();
});
test("fotografia aguarda vídeo pronto e produz JPEG com tamanho limitado", async () => {
    await assert.rejects(
        photograph({ videoWidth: 0, videoHeight: 0 }),
        /Aguarde/,
    );
    const draw = [];
    const canvas = {
        getContext: () => ({ drawImage: (...args) => draw.push(args) }),
        toBlob: (callback) =>
            callback(new Blob(["jpeg-sintetico"], { type: "image/jpeg" })),
    };
    const photo = await photograph(
        { videoWidth: 4000, videoHeight: 3000 },
        () => canvas,
    );
    assert.equal(canvas.width, 1600);
    assert.equal(canvas.height, 1200);
    assert.equal(draw.length, 1);
    assert.equal(photo.type, "image/jpeg");
    assert.equal(photo.name, "fotografia.jpg");
});
