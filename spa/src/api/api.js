import conference from "../pages/conference";

function fetchCollection(path){
    return fetch(ENV_API_ENDPOINT + path).then(resp=>resp.json()).then(json =>json['hydra:member']);
}

export function findConferences() {
    return fetchCollection('api/conferences')
}

export function findComments(Conference) {
    return fetchCollection('api/comments?conference='+conference.id);
}
