<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentsController extends Controller
{
    public function store(Request $request)
    {
        try{

            //validate the data first;
            $result = $this->validateRequestedData($request);
            if(!$result['status']){
                return response()->json($result['errors'],Response::HTTP_BAD_REQUEST);
            }
            $result = $this->getCountry($request['country_code']);
            if(!$result['status']){
                // if country code not found
                return response()->json($result['message'],Response::HTTP_NOT_FOUND);
            }
            $request['country'] = $result['country'];
            $result = $this->createStudent($request);
            if(!$result['status']){
                //if student not created
                return response()->json($result['message'],Response::HTTP_BAD_REQUEST);
            }
            Log::info('Student Has Been created successfully');
            return response()->json(['status' => 201,'message' => 'Student Information Stored Successfully','data' => $result['data']]);
        }catch(\Exception $e)
        {
            DB::rollback();
            Log::info('Transaction has been rollbacked for the following reason\n');
            Log::info($e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to Store Data !']);
        }
    }

    // @return array
    private function validateRequestedData($request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required',
            'phone_number' => 'required|integer|digits:10',
            'email' => 'required|email',
            'country_code' => 'required|integer'
        ]);
        if($validator->fails())
        {
            return ['status' => false,'errors' => $validator->errors()];
        }
        return ['status' => true, 'errors' => []];
    }

    private function getCountry($country_code)
    {
        try{
            $response = Http::get('https://restcountries.eu/rest/v2/callingcode/'.$country_code);
            //if response has status of 404 not found and return not found;
            if(isset($response['status'])){
                return ['status' => false,'message' => "Country Not Found"];
            }
            return ['status' => true, 'country' => $response[0]['name']];
        }catch(\Exception $e)
        {
            //log if any thing wrong in connecting to api
            Log::info($e->getMessage());
            return ['status' => false, 'message' => "failed to connect to api"];
        }
    }

    private function createStudent($request)
    {
        try{
            //Start Transaction
            DB::beginTransaction();
            $student = Student::create([
                'name' => $request['name'],
                'email' => $request['email'],
                'phone_number' => $request['phone_number'],
                'country' => $request['country'],
                'country_code' => $request['country_code']
            ]);
            DB::commit();
            Log::info($student);
            //End Transaction
            return ['status' => true,'message' => "Student Created Successfully" , 'data' => $student];
        }catch(\Exception $e){
            DB::rollback();
            Log::info('Transaction has been rollbacked for the following reason\n');
            Log::info($e->getMessage());
            return ['status' => false, 'message' => 'Failed to Store Data !', 'data' => null];
        }
    }



    //search the student

    public function searchStudent($param)
    {
        try{
            if(trim($param) == ""){
                return response()->json(['message' => "No params passed"],Response::HTTP_NOT_FOUND);
            }
            $student = Student::where('name','LIKE','%'.$param.'%')
                                ->orWhere('email','LIKE','%'.$param.'%')
                                ->orWhere('phone_number','LIKE','%'.$param.'%')
                                ->orWhere('country','LIKE','%'.$param.'%')
                                ->paginate(5);
            if($student->isNotEmpty())
            {
                return response()->json(['message' => "total ".$student->count()." students found",'students' => $student],Response::HTTP_OK);
            }
            return response()->json(['message' => "students Not found",'students' => null],Response::HTTP_NOT_FOUND);
        }catch(\Exception $e){
            //if any thing goes wrong
            Log::info($e);
            return response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }
}
