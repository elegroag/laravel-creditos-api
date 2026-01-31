const Ziggy = {"url":"http:\/\/localhost:8000","port":8000,"defaults":{},"routes":{"sanctum.csrf-cookie":{"uri":"sanctum\/csrf-cookie","methods":["GET","HEAD"]},"welcome":{"uri":"\/","methods":["GET","HEAD"]},"login":{"uri":"web\/login","methods":["GET","HEAD"]},"adviser.login":{"uri":"web\/adviser","methods":["GET","HEAD"]},"register":{"uri":"web\/register","methods":["GET","HEAD"]},"verify.show":{"uri":"web\/verify","methods":["GET","HEAD"]},"verify.store":{"uri":"web\/verify","methods":["POST"]},"inicio":{"uri":"inicio","methods":["GET","HEAD"]},"logout":{"uri":"logout","methods":["POST"]},"dashboard":{"uri":"dashboard","methods":["GET","HEAD"]},"perfil":{"uri":"perfil","methods":["GET","HEAD"]},"storage.local":{"uri":"storage\/{path}","methods":["GET","HEAD"],"wheres":{"path":".*"},"parameters":["path"]}}};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
  Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
