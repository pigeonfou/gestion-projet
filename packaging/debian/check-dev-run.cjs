const fs=require('node:fs');
module.exports=async({github,context,core})=>{
 const b=JSON.parse(fs.readFileSync('packaging/debian/dev-baseline.json','utf8'));
 const {data:run}=await github.rest.actions.getWorkflowRun({...context.repo,run_id:b.workflow_run_id});
 if(run.path!=='.github/workflows/deploy.yml'||run.head_branch!=='main'||run.head_sha!==b.commit||run.conclusion!=='success'||run.event!=='push')throw Error('Baseline DEV non déployée par le workflow officiel');
 const jobs=await github.paginate(github.rest.actions.listJobsForWorkflowRun,{...context.repo,run_id:b.workflow_run_id,per_page:100});
 if(!jobs.some(j=>j.name==='deploy'&&j.conclusion==='success'))throw Error('Job de déploiement DEV non réussi');
 core.info('Déploiement DEV vérifié : '+run.html_url+' — '+b.commit);
};
